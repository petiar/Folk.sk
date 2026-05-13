<?php

namespace Drupal\folk_submit\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SubmitEventForm extends FormBase implements TrustedCallbackInterface {

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
    $form['#attributes']['class'][] = 'folk-submit-form';

    // ── Sekcia: Základné info ───────────────────────────────────────────────
    $form['section_basic'] = [
      '#type'       => 'container',
      '#tree'       => TRUE,
      '#attributes' => ['class' => ['folk-submit-section']],
    ];
    $form['section_basic']['heading'] = [
      '#markup' => '<h2 class="folk-submit-section__title">O akcii</h2>',
    ];
    $form['section_basic']['title'] = [
      '#type'        => 'textfield',
      '#title'       => $this->t('Názov akcie'),
      '#required'    => TRUE,
      '#maxlength'   => 255,
      '#placeholder' => $this->t('Koncert folkovej hviezdy...'),
      '#attributes'  => ['class' => ['form-control-lg']],
    ];
    $form['section_basic']['body'] = [
      '#type'            => 'text_format',
      '#title'           => $this->t('Popis akcie'),
      '#required'        => FALSE,
      '#format'          => 'basic_html',
      '#allowed_formats' => ['basic_html', 'full_html'],
      '#rows'            => 8,
    ];

    // ── Sekcie: Kedy a kde + Doplňujúce — vedľa seba ──────────────────────
    $form['sections_row'] = [
      '#type'       => 'container',
      '#tree'       => TRUE,
      '#attributes' => ['class' => ['row', 'g-4']],
    ];

    // Kedy a kde
    $form['sections_row']['when'] = [
      '#type'       => 'container',
      '#tree'       => TRUE,
      '#attributes' => ['class' => ['col-md-7', 'folk-submit-section']],
      'heading'     => ['#markup' => '<h2 class="folk-submit-section__title">Kedy a kde</h2>'],
      'datetime_row' => [
        '#type'       => 'container',
        '#tree'       => TRUE,
        '#attributes' => ['class' => ['folk-submit-datetime-row']],
        'datum' => [
          '#type'     => 'date',
          '#title'    => $this->t('Dátum'),
          '#required' => TRUE,
        ],
        'cas' => [
          '#type'       => 'textfield',
          '#title'      => $this->t('Čas'),
          '#required'   => FALSE,
          '#size'       => 8,
          '#maxlength'  => 5,
          '#pre_render' => [[static::class, 'setTimeType']],
        ],
      ],
      'field_miesto' => [
        '#type'        => 'textfield',
        '#title'       => $this->t('Miesto konania'),
        '#required'    => TRUE,
        '#placeholder' => $this->t('Napr. Amfiteáter Trenčín'),
      ],
      'field_mesto' => [
        '#type'         => 'select',
        '#title'        => $this->t('Mesto'),
        '#options'      => $this->getTermOptions('mesto'),
        '#empty_option' => $this->t('- Vyberte mesto -'),
        '#required'     => TRUE,
      ],
    ];

    // Doplňujúce informácie
    $form['sections_row']['extra'] = [
      '#type'       => 'container',
      '#tree'       => TRUE,
      '#attributes' => ['class' => ['col-md-5', 'folk-submit-section']],
      'heading'     => ['#markup' => '<h2 class="folk-submit-section__title">Doplňujúce informácie</h2>'],
      'field_vstupne' => [
        '#type'        => 'textfield',
        '#title'       => $this->t('Vstupné'),
        '#required'    => FALSE,
        '#placeholder' => $this->t('napr. 5 €, dobrovoľné, predpredaj 8 € / na mieste 10 €'),
        '#maxlength'   => 255,
      ],
      'field_akcia_webstranka' => [
        '#type'        => 'url',
        '#title'       => $this->t('Web akcie'),
        '#required'    => FALSE,
        '#placeholder' => 'https://',
        '#maxlength'   => 2048,
      ],
      'field_titulny_obrazok' => [
        '#type'              => 'managed_file',
        '#title'             => $this->t('Plagát / obrázok akcie'),
        '#description'       => $this->t('jpg, png, gif, webp — max. 10 MB'),
        '#upload_location'   => 'public://akcie/' . date('Y/m'),
        '#upload_validators' => [
          'FileExtension' => ['extensions' => 'jpg jpeg png gif webp'],
          'FileSizeLimit' => ['fileLimit' => 10 * 1024 * 1024],
        ],
        '#multiple' => FALSE,
      ],
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type'        => 'submit',
      '#value'       => $this->t('Odoslať akciu na schválenie'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $node = $this->entityTypeManager->getStorage('node')->create([
      'type'     => 'akcia',
      'title'    => $form_state->getValue(['section_basic', 'title']),
      'uid'      => $this->currentUser->id(),
      'status'   => 0,
      'langcode' => $this->languageManager->getCurrentLanguage()->getId(),
    ]);

    // Dátum + čas — hodnoty sú vnorené v kontajneroch
    $datum = $form_state->getValue(['sections_row', 'when', 'datetime_row', 'datum']) ?? '';
    $cas   = $form_state->getValue(['sections_row', 'when', 'datetime_row', 'cas'])   ?? '00:00';
    if ($datum) {
      $node->set('field_datumcas', $datum . 'T' . ($cas ?: '00:00') . ':00');
    }

    $miesto = $form_state->getValue(['sections_row', 'when', 'field_miesto']) ?? '';
    if ($miesto) {
      $node->set('field_miesto', $miesto);
    }

    $mesto = $form_state->getValue(['sections_row', 'when', 'field_mesto']) ?? '';
    if ($mesto) {
      $node->set('field_mesto', ['target_id' => $mesto]);
    }

    $body = $form_state->getValue(['section_basic', 'body']);
    if (!empty($body['value'])) {
      $node->set('body', ['value' => $body['value'], 'format' => $body['format']]);
    }

    $vstupne = $form_state->getValue(['sections_row', 'extra', 'field_vstupne']) ?? '';
    if ($vstupne !== '') {
      $node->set('field_vstupne', $vstupne);
    }

    $web = $form_state->getValue(['sections_row', 'extra', 'field_akcia_webstranka']) ?? '';
    if ($web) {
      $node->set('field_akcia_webstranka', ['uri' => $web]);
    }

    $fid = $form_state->getValue(['sections_row', 'extra', 'field_titulny_obrazok']) ?? NULL;
    if ($fid) {
      $file = $this->entityTypeManager->getStorage('file')->load(is_array($fid) ? reset($fid) : $fid);
      if ($file) {
        $file->setPermanent();
        $file->save();
        $node->set('field_titulny_obrazok', ['target_id' => $file->id(), 'alt' => $node->label()]);
      }
    }

    $node->save();
    $this->notifyAdmins($node);

    $this->messenger()->addStatus($this->t(
      'Ďakujeme! Akcia „@title" bola odoslaná a čaká na schválenie.',
      ['@title' => $node->label()]
    ));

    $form_state->setRedirect('<front>');
  }

  public static function trustedCallbacks(): array {
    return ['setTimeType'];
  }

  public static function setTimeType(array $element): array {
    $element['#attributes']['type'] = 'time';
    return $element;
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
