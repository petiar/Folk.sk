<?php

namespace Drupal\folk_muzikant\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

class FindMuzikantForm extends FormBase {

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
    return 'folk_muzikant_find_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {

    // Existujúce nárokované profily — zobraz ich, ale nezastavuj
    $existing = $this->entityTypeManager->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => 'muzikant', 'field_user' => $this->currentUser->id()]);
    if ($existing) {
      $links = [];
      foreach ($existing as $term) {
        $edit_url = Url::fromRoute('folk_muzikant.edit', ['taxonomy_term' => $term->id()])->toString();
        $links[] = '<a href="' . $term->toUrl()->toString() . '">' . $term->label() . '</a>'
          . ' <a href="' . $edit_url . '" class="btn btn-xs btn-outline-primary btn-sm ms-1">upraviť</a>';
      }
      $form['existing'] = [
        '#markup' => '<div class="alert alert-info mb-3">'
          . '<strong>' . $this->t('Vaše aktuálne profily:') . '</strong> '
          . implode(', ', $links)
          . '</div>',
      ];
    }

    $form['intro'] = [
      '#markup' => '<p>' . $this->t('Najprv skúste nájsť svoj profil medzi existujúcimi muzikantmi. Začnite písať svoje meno alebo názov kapely.') . '</p>',
    ];

    $form['muzikant'] = [
      '#type'                => 'entity_autocomplete',
      '#title'               => $this->t('Hľadať muzikanta / kapelu'),
      '#target_type'         => 'taxonomy_term',
      '#selection_settings'  => ['target_bundles' => ['muzikant']],
      '#placeholder'         => $this->t('Začnite písať meno...'),
      '#required'            => FALSE,
      '#size'                => 60,
    ];

    $form['actions'] = ['#type' => 'actions'];

    $form['actions']['claim'] = [
      '#type'        => 'submit',
      '#value'       => $this->t('✓ To je môj profil — nárokovať'),
      '#button_type' => 'primary',
      '#submit'      => ['::submitClaim'],
      '#attributes'  => ['class' => ['me-2']],
    ];

    $form['actions']['create'] = [
      '#type'       => 'link',
      '#title'      => $this->t('Nenašiel som, vytvorím nový profil →'),
      '#url'        => Url::fromRoute('folk_muzikant.create'),
      '#attributes' => ['class' => ['btn', 'btn-outline-secondary']],
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // Validácia len pre claim submit
    if ($form_state->getTriggeringElement()['#submit'][0] !== '::submitClaim') return;

    if (!$form_state->getValue('muzikant')) {
      $form_state->setErrorByName('muzikant', $this->t('Vyberte muzikanta zo zoznamu.'));
      return;
    }

    $term = $this->entityTypeManager->getStorage('taxonomy_term')
      ->load($form_state->getValue('muzikant'));

    if (!$term || $term->bundle() !== 'muzikant') {
      $form_state->setErrorByName('muzikant', $this->t('Neplatný muzikant.'));
      return;
    }

    $owner_id = $term->get('field_user')->target_id;
    if ($owner_id && (int) $owner_id !== (int) $this->currentUser->id()) {
      $form_state->setErrorByName('muzikant', $this->t('Tento profil bol už nárokovaný iným používateľom.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {}

  public function submitClaim(array &$form, FormStateInterface $form_state): void {
    $tid  = $form_state->getValue('muzikant');
    $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($tid);

    $term->set('field_user', ['target_id' => $this->currentUser->id()]);
    $term->save();

    $this->messenger()->addStatus($this->t(
      'Profil „@name" bol úspešne nárokovaný!',
      ['@name' => $term->label()]
    ));

    $form_state->setRedirectUrl(
      Url::fromRoute('folk_muzikant.edit', ['taxonomy_term' => $tid])
    );
  }

}
