<?php

namespace Drupal\folk_muzikant\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class EditMuzikantForm extends FormBase {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    RouteMatchInterface $routeMatch,
    protected AccountProxyInterface $currentUser,
  ) {
    $this->routeMatch = $routeMatch;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_route_match'),
      $container->get('current_user'),
    );
  }

  public function getFormId(): string {
    return 'folk_muzikant_edit_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $term = $this->routeMatch->getParameter('taxonomy_term');

    $form['#attributes']['enctype'] = 'multipart/form-data';

    $form['name'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Názov / meno'),
      '#required'      => TRUE,
      '#default_value' => $term->label(),
    ];

    $form['description'] = [
      '#type'          => 'text_format',
      '#title'         => $this->t('Popis / bio'),
      '#format'        => 'basic_html',
      '#allowed_formats' => ['basic_html', 'full_html'],
      '#default_value' => $term->get('description')->value,
      '#rows'          => 8,
    ];

    $form['field_foto'] = [
      '#type'              => 'managed_file',
      '#title'             => $this->t('Fotografia'),
      '#upload_location'   => 'public://muzikanti/',
      '#upload_validators' => [
        'FileExtension' => ['extensions' => 'jpg jpeg png gif webp'],
        'FileSizeLimit' => ['fileLimit' => 5 * 1024 * 1024],
      ],
      '#default_value'     => $term->get('field_foto')->target_id
        ? [$term->get('field_foto')->target_id]
        : [],
    ];

    // Weblinky (max 3)
    $form['field_web'] = [
      '#type'        => 'fieldset',
      '#title'       => $this->t('Webstránky / sociálne siete'),
      '#tree'        => TRUE,
    ];
    $existing_links = $term->get('field_web')->getValue();
    for ($i = 0; $i < 3; $i++) {
      $form['field_web'][$i] = [
        '#type'          => 'url',
        '#title'         => $this->t('Odkaz @n', ['@n' => $i + 1]),
        '#default_value' => $existing_links[$i]['uri'] ?? '',
        '#placeholder'   => 'https://',
        '#required'      => FALSE,
      ];
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type'        => 'submit',
      '#value'       => $this->t('Uložiť profil'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $term = $this->routeMatch->getParameter('taxonomy_term');

    $term->setName($form_state->getValue('name'));

    $desc = $form_state->getValue('description');
    $term->set('description', [
      'value'  => $desc['value'],
      'format' => $desc['format'],
    ]);

    // Fotografia
    $fids = $form_state->getValue('field_foto') ?? [];
    if ($fids) {
      $fid = is_array($fids) ? reset($fids) : $fids;
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
    $term->set('field_web', $links);

    $term->save();

    $this->messenger()->addStatus($this->t('Profil bol uložený.'));
    $form_state->setRedirectUrl($term->toUrl());
  }

}
