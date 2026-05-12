<?php

namespace Drupal\folk_submit\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SubmitEventForm extends FormBase {

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
    return 'folk_submit_event_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {

    if ($this->currentUser->isAnonymous()) {
      return [
        '#type' => 'container',
        '#attributes' => ['class' => ['folk-submit-anon-notice']],
        'message' => [
          '#markup' => '<p>' . $this->t('Akcie môžu pridávať iba registrovaní používatelia.') . '</p>',
        ],
        'actions' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['d-flex', 'gap-2', 'mt-3']],
          'register' => [
            '#type'       => 'link',
            '#title'      => $this->t('Registrovať sa'),
            '#url'        => Url::fromRoute('user.register'),
            '#attributes' => ['class' => ['btn', 'btn-primary']],
          ],
          'login' => [
            '#type'       => 'link',
            '#title'      => $this->t('Prihlásiť sa'),
            '#url'        => Url::fromRoute('user.login', [], ['query' => ['destination' => '/pridat-akciu']]),
            '#attributes' => ['class' => ['btn', 'btn-outline-secondary']],
          ],
        ],
      ];
    }

    $form['#attributes']['enctype'] = 'multipart/form-data';

    $form['title'] = [
      '#type'        => 'textfield',
      '#title'       => $this->t('Názov akcie'),
      '#required'    => TRUE,
      '#maxlength'   => 255,
      '#placeholder' => $this->t('Napr. Lodenica 2025 – Komorná scéna'),
    ];

    $form['field_datumcas'] = [
      '#type'     => 'datetime',
      '#title'    => $this->t('Dátum a čas'),
      '#required' => TRUE,
      '#date_date_element' => 'date',
      '#date_time_element' => 'time',
    ];

    $form['field_miesto'] = [
      '#type'        => 'textarea',
      '#title'       => $this->t('Miesto konania'),
      '#required'    => TRUE,
      '#rows'        => 2,
      '#placeholder' => $this->t('Napr. Amfiteáter, Trenčín'),
    ];

    $form['field_mesto'] = [
      '#type'         => 'select',
      '#title'        => $this->t('Mesto'),
      '#options'      => $this->getTermOptions('mesto'),
      '#empty_option' => $this->t('- Vyberte mesto -'),
      '#required'     => TRUE,
    ];

    $form['body'] = [
      '#type'     => 'text_format',
      '#title'    => $this->t('Popis akcie'),
      '#required' => FALSE,
      '#format'   => 'basic_html',
      '#allowed_formats' => ['basic_html', 'full_html'],
      '#rows'     => 10,
    ];

    $form['field_akcia_webstranka'] = [
      '#type'        => 'url',
      '#title'       => $this->t('Web akcie'),
      '#required'    => FALSE,
      '#placeholder' => 'https://',
      '#maxlength'   => 2048,
    ];

    $form['field_titulny_obrazok'] = [
      '#type'              => 'managed_file',
      '#title'             => $this->t('Obrázok'),
      '#description'       => $this->t('Povolené formáty: jpg, jpeg, png, gif, webp. Max. 10 MB.'),
      '#upload_location'   => 'public://akcie/' . date('Y/m'),
      '#upload_validators' => [
        'FileExtension' => ['extensions' => 'jpg jpeg png gif webp'],
        'FileSizeLimit' => ['fileLimit' => 10 * 1024 * 1024],
      ],
      '#multiple'          => FALSE,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type'        => 'submit',
      '#value'       => $this->t('Odoslať akciu'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->getValues();

    $node = $this->entityTypeManager->getStorage('node')->create([
      'type'     => 'akcia',
      'title'    => $values['title'],
      'uid'      => $this->currentUser->id(),
      'status'   => 0,
      'langcode' => $this->languageManager->getCurrentLanguage()->getId(),
    ]);

    // Dátum a čas
    if (!empty($values['field_datumcas'])) {
      $node->set('field_datumcas', $values['field_datumcas']->format('Y-m-d\TH:i:s'));
    }

    // Miesto
    if (!empty($values['field_miesto'])) {
      $node->set('field_miesto', $values['field_miesto']);
    }

    // Mesto (taxonomy)
    if (!empty($values['field_mesto'])) {
      $node->set('field_mesto', ['target_id' => $values['field_mesto']]);
    }

    // Popis
    if (!empty($values['body']['value'])) {
      $node->set('body', [
        'value'  => $values['body']['value'],
        'format' => $values['body']['format'],
      ]);
    }

    // Web
    if (!empty($values['field_akcia_webstranka'])) {
      $node->set('field_akcia_webstranka', ['uri' => $values['field_akcia_webstranka']]);
    }

    // Obrázok
    $fid = $values['field_titulny_obrazok'] ?? NULL;
    if ($fid) {
      $file = $this->entityTypeManager->getStorage('file')->load(is_array($fid) ? reset($fid) : $fid);
      if ($file) {
        $file->setPermanent();
        $file->save();
        $node->set('field_titulny_obrazok', ['target_id' => $file->id(), 'alt' => $values['title']]);
      }
    }

    $node->save();
    $this->notifyAdmins($node);

    $this->messenger()->addStatus($this->t(
      'Ďakujeme! Akcia „@title" bola odoslaná a čaká na schválenie.',
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
    $admins    = $this->entityTypeManager->getStorage('user')->loadByProperties(['roles' => 'administrator', 'status' => 1]);
    $config    = $this->config('system.site');
    $node_url  = $node->toUrl('edit-form')->setAbsolute()->toString();

    $params = [
      'node'      => $node,
      'author'    => $node->getOwner()->getAccountName(),
      'node_url'  => $node_url,
      'site_name' => $config->get('name'),
      'type'      => 'event',
    ];

    foreach ($admins as $admin) {
      if (!$admin->getEmail()) continue;
      $this->mailManager->mail('folk_submit', 'new_article', $admin->getEmail(), $admin->getPreferredLangcode(), $params, $config->get('mail'));
    }
  }

}
