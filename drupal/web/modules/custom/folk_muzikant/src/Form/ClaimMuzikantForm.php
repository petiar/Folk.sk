<?php

namespace Drupal\folk_muzikant\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ClaimMuzikantForm extends FormBase {

  public function __construct(
    protected AccountProxyInterface $currentUser,
    RouteMatchInterface $routeMatch,
  ) {
    $this->routeMatch = $routeMatch;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('current_user'),
      $container->get('current_route_match'),
    );
  }

  public function getFormId(): string {
    return 'folk_muzikant_claim_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    /** @var TermInterface $term */
    $term = $this->routeMatch->getParameter('taxonomy_term');

    if (!$term || $term->bundle() !== 'muzikant') {
      $form['error'] = ['#markup' => '<p>' . $this->t('Neplatný muzikant.') . '</p>'];
      return $form;
    }

    $owner_id = $term->get('field_user')->target_id;

    // Už nárokovaný iným používateľom
    if ($owner_id && (int) $owner_id !== (int) $this->currentUser->id()) {
      $form['notice'] = [
        '#markup' => '<div class="alert alert-warning">'
          . $this->t('Tento profil bol už nárokovaný iným používateľom.')
          . '</div>',
      ];
      return $form;
    }

    // Už nárokovaný týmto používateľom
    if ($owner_id && (int) $owner_id === (int) $this->currentUser->id()) {
      $edit_url = Url::fromRoute('folk_muzikant.edit', ['taxonomy_term' => $term->id()])->toString();
      $form['notice'] = [
        '#markup' => '<div class="alert alert-success">'
          . $this->t('Tento profil ste si už nárokovali.')
          . ' <a href="' . $edit_url . '">' . $this->t('Upraviť profil') . '</a>'
          . '</div>',
      ];
      return $form;
    }

    $form['info'] = [
      '#markup' => '<p>' . $this->t(
        'Chcete si nárokovať profil muzikanta <strong>@name</strong>? Po nárokovaní ho budete môcť upravovať.',
        ['@name' => $term->label()]
      ) . '</p>',
    ];

    $form['term_id'] = [
      '#type'  => 'hidden',
      '#value' => $term->id(),
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type'        => 'submit',
      '#value'       => $this->t('Nárokovať profil'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $tid = $form_state->getValue('term_id');
    $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($tid);

    if (!$term) return;

    $term->set('field_user', ['target_id' => $this->currentUser->id()]);
    $term->save();

    $this->messenger()->addStatus($this->t(
      'Profil „@name" bol úspešne nárokovaný. Teraz ho môžete upraviť.',
      ['@name' => $term->label()]
    ));

    $form_state->setRedirectUrl(
      Url::fromRoute('folk_muzikant.edit', ['taxonomy_term' => $tid])
    );
  }

}
