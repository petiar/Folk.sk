<?php

namespace Drupal\folk_submit\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SubmitArticleForm extends FormBase {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected MailManagerInterface $mailManager,
    protected AccountProxyInterface $currentUser,
    protected LanguageManagerInterface $languageManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.mail'),
      $container->get('current_user'),
      $container->get('language_manager'),
    );
  }

  public function getFormId(): string {
    return 'folk_submit_article_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {

    // Anonymný používateľ — zobraz oznam namiesto formulára
    if ($this->currentUser->isAnonymous()) {
      return [
        '#type'  => 'container',
        '#attributes' => ['class' => ['folk-submit-anon-notice']],
        'message' => [
          '#markup' => '<p>' . $this->t('Články môžu pridávať iba registrovaní používatelia.') . '</p>',
        ],
        'actions' => [
          '#type'  => 'container',
          '#attributes' => ['class' => ['d-flex', 'gap-2', 'mt-3']],
          'register' => [
            '#type'  => 'link',
            '#title' => $this->t('Registrovať sa'),
            '#url'   => \Drupal\Core\Url::fromRoute('user.register'),
            '#attributes' => ['class' => ['btn', 'btn-primary']],
          ],
          'login' => [
            '#type'  => 'link',
            '#title' => $this->t('Prihlásiť sa'),
            '#url'   => \Drupal\Core\Url::fromRoute('user.login', [], ['query' => ['destination' => '/pridat-clanok']]),
            '#attributes' => ['class' => ['btn', 'btn-outline-secondary']],
          ],
        ],
      ];
    }

    $form['#attributes']['enctype'] = 'multipart/form-data';

    $form['title'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Názov článku'),
      '#required'      => TRUE,
      '#maxlength'     => 255,
      '#placeholder'   => $this->t('Zadajte výstižný názov...'),
    ];

    $form['body'] = [
      '#type'          => 'text_format',
      '#title'         => $this->t('Text článku'),
      '#required'      => TRUE,
      '#format'        => 'full_html',
      '#allowed_formats' => ['full_html', 'basic_html'],
      '#rows'          => 20,
    ];

    $form['images'] = [
      '#type'              => 'managed_file',
      '#title'             => $this->t('Obrázky (max. 10)'),
      '#description'       => $this->t('Povolené formáty: jpg, jpeg, png, gif, webp. Prvý obrázok bude použitý ako titulný.'),
      '#upload_location'   => 'public://clanky/' . date('Y/m'),
      '#upload_validators' => [
        'FileExtension'   => ['extensions' => 'jpg jpeg png gif webp'],
        'FileSizeLimit'   => ['fileLimit' => 10 * 1024 * 1024],
      ],
      '#multiple'          => TRUE,
      '#cardinality'       => 10,
    ];

    $form['field_co_nam_pisete_'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Čo nám píšete?'),
      '#options'       => $this->getTermOptions('co_nam_pisete_'),
      '#empty_option'  => $this->t('- Vyberte kategóriu -'),
      '#required'      => TRUE,
    ];

    $form['field_o_com_nam_pisete_'] = [
      '#type'          => 'select',
      '#title'         => $this->t('O čom nám píšete?'),
      '#options'       => $this->getTermOptions('o_com_nam_pisete_'),
      '#empty_option'  => $this->t('- Vyberte tému -'),
      '#required'      => TRUE,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type'        => 'submit',
      '#value'       => $this->t('Odoslať článok'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $images = array_filter($form_state->getValue('images', []));
    if (count($images) > 10) {
      $form_state->setErrorByName('images', $this->t('Môžete nahrať maximálne 10 obrázkov.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $values  = $form_state->getValues();
    $storage = $this->entityTypeManager->getStorage('node');

    // Vytvor článok
    $node = $storage->create([
      'type'      => 'clanok',
      'title'     => $values['title'],
      'body'      => [
        'value'  => $values['body']['value'],
        'format' => $values['body']['format'],
      ],
      'uid'       => $this->currentUser->id(),
      'status'    => 0, // nezverejnený
      'langcode'  => $this->languageManager->getCurrentLanguage()->getId(),
    ]);

    // Kategórie
    if (!empty($values['field_co_nam_pisete_'])) {
      $node->set('field_co_nam_pisete_', ['target_id' => $values['field_co_nam_pisete_']]);
    }
    if (!empty($values['field_o_com_nam_pisete_'])) {
      $node->set('field_o_com_nam_pisete_', ['target_id' => $values['field_o_com_nam_pisete_']]);
    }

    // Obrázky — prvý ako titulný
    $images = array_values(array_filter($values['images'] ?? []));
    if (!empty($images)) {
      $file_storage = $this->entityTypeManager->getStorage('file');

      // Titulný obrázok = prvý
      $first_file = $file_storage->load($images[0]);
      if ($first_file) {
        $first_file->setPermanent();
        $first_file->save();
        $node->set('field_titulny_obrazok', [
          'target_id' => $first_file->id(),
          'alt'       => $values['title'],
        ]);
      }

      // Ostatné obrázky — nastav ako permanent
      foreach (array_slice($images, 1) as $fid) {
        $file = $file_storage->load($fid);
        if ($file) {
          $file->setPermanent();
          $file->save();
        }
      }
    }

    $node->save();

    // Email administrátorom
    $this->notifyAdmins($node);

    $this->messenger()->addStatus($this->t(
      'Ďakujeme! Váš článok „@title" bol odoslaný a čaká na schválenie.',
      ['@title' => $values['title']]
    ));

    $form_state->setRedirect('<front>');
  }

  private function getTermOptions(string $vocabulary): array {
    $terms = $this->entityTypeManager
      ->getStorage('taxonomy_term')
      ->loadTree($vocabulary, 0, NULL, TRUE);

    $options = [];
    foreach ($terms as $term) {
      $options[$term->id()] = str_repeat('–', $term->depth) . ($term->depth ? ' ' : '') . $term->label();
    }
    return $options;
  }

  private function notifyAdmins(\Drupal\node\NodeInterface $node): void {
    // Nájdi všetkých adminov s emailom
    $admins = $this->entityTypeManager->getStorage('user')
      ->loadByProperties(['roles' => 'administrator', 'status' => 1]);

    $site_config = $this->config('system.site');
    $site_name   = $site_config->get('name');
    $site_mail   = $site_config->get('mail');

    $author = $node->getOwner();
    $node_url = $node->toUrl('edit-form')->setAbsolute()->toString();

    $params = [
      'node'      => $node,
      'author'    => $author->getAccountName(),
      'node_url'  => $node_url,
      'site_name' => $site_name,
    ];

    foreach ($admins as $admin) {
      if (!$admin->getEmail()) continue;
      $this->mailManager->mail(
        'folk_submit',
        'new_article',
        $admin->getEmail(),
        $admin->getPreferredLangcode(),
        $params,
        $site_mail,
      );
    }
  }

}
