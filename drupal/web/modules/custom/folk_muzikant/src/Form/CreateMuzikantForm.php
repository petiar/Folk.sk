<?php

namespace Drupal\folk_muzikant\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CreateMuzikantForm extends FormBase {

  public function __construct(
    protected AccountProxyInterface $currentUser,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('current_user'),
      $container->get('entity_type.manager'),
    );
  }

  public function getFormId(): string {
    return 'folk_muzikant_create_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {

    // Existujúce profily — zobraziť, ale nezastaviť
    $existing = $this->entityTypeManager->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => 'muzikant', 'field_user' => $this->currentUser->id()]);
    if ($existing) {
      $links = [];
      foreach ($existing as $t) {
        $links[] = '<a href="' . $t->toUrl()->toString() . '">' . $t->label() . '</a>';
      }
      $form['existing'] = [
        '#markup' => '<div class="alert alert-info mb-3">'
          . $this->t('Už máte tieto profily: ')
          . implode(', ', $links)
          . '. ' . $this->t('Môžete si vytvoriť ďalší (napr. pre druhú kapelu).')
          . '</div>',
      ];
    }

    $form['#attributes']['enctype'] = 'multipart/form-data';

    $form['back'] = [
      '#markup' => '<p><a href="' . Url::fromRoute('folk_muzikant.find')->toString() . '">← Späť na hľadanie</a></p>',
    ];

    $form['name'] = [
      '#type'        => 'textfield',
      '#title'       => $this->t('Meno / názov kapely'),
      '#required'    => TRUE,
      '#maxlength'   => 255,
      '#placeholder' => $this->t('Napr. Peter Novák alebo Folklórny súbor Lipa'),
    ];

    $form['description'] = [
      '#type'   => 'text_format',
      '#title'  => $this->t('O mne / o kapele'),
      '#format' => 'basic_html',
      '#allowed_formats' => ['basic_html', 'full_html'],
      '#rows'   => 8,
    ];

    $form['field_foto'] = [
      '#type'              => 'managed_file',
      '#title'             => $this->t('Fotografia'),
      '#upload_location'   => 'public://muzikanti/',
      '#upload_validators' => [
        'FileExtension' => ['extensions' => 'jpg jpeg png gif webp'],
        'FileSizeLimit' => ['fileLimit' => 5 * 1024 * 1024],
      ],
    ];

    $form['field_web'] = [
      '#type'  => 'fieldset',
      '#title' => $this->t('Webstránky / sociálne siete'),
      '#tree'  => TRUE,
    ];
    for ($i = 0; $i < 3; $i++) {
      $form['field_web'][$i] = [
        '#type'        => 'url',
        '#title'       => $this->t('Odkaz @n', ['@n' => $i + 1]),
        '#placeholder' => 'https://',
        '#required'    => FALSE,
      ];
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type'        => 'submit',
      '#value'       => $this->t('Vytvoriť a uložiť profil'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Ak sme v AJAX dialógu, vrátime AjaxResponse
    if ($this->getRequest()->isXmlHttpRequest()) {
      $form_state->disableRedirect();
    }

    $storage = $this->entityTypeManager->getStorage('taxonomy_term');

    // Vytvor nový taxonomy term
    $term = $storage->create([
      'vid'         => 'muzikant',
      'name'        => $form_state->getValue('name'),
      'description' => [
        'value'  => $form_state->getValue('description')['value'] ?? '',
        'format' => $form_state->getValue('description')['format'] ?? 'basic_html',
      ],
      'field_user'  => ['target_id' => $this->currentUser->id()],
    ]);

    // Fotografia
    $fids = $form_state->getValue('field_foto') ?? [];
    if ($fids) {
      $fid  = is_array($fids) ? reset($fids) : $fids;
      $file = $this->entityTypeManager->getStorage('file')->load($fid);
      if ($file) {
        $file->setPermanent();
        $file->save();
        $term->set('field_foto', ['target_id' => $file->id()]);
      }
    }

    // Weblinky
    $links = [];
    foreach ($form_state->getValue('field_web') ?? [] as $url) {
      if (!empty($url)) {
        $links[] = ['uri' => $url, 'title' => ''];
      }
    }
    if ($links) $term->set('field_web', $links);

    $term->save();

    $this->messenger()->addStatus($this->t(
      'Profil „@name" bol vytvorený. Môžete ho ďalej upravovať.',
      ['@name' => $term->label()]
    ));

    if ($this->getRequest()->isXmlHttpRequest()) {
      $response = new AjaxResponse();
      $response->addCommand(new CloseModalDialogCommand());
      $response->addCommand(new MessageCommand(
        $this->t('Profil „@name" bol vytvorený. Vyhľadajte ho teraz v poli Účinkujúci.', ['@name' => $term->label()]),
        NULL,
        ['type' => 'status']
      ));
      $form_state->setResponse($response);
      return;
    }

    $form_state->setRedirectUrl(
      Url::fromRoute('folk_muzikant.edit', ['taxonomy_term' => $term->id()])
    );
  }

}
