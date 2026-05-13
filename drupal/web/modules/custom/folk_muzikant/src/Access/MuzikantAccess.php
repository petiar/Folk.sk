<?php

namespace Drupal\folk_muzikant\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\taxonomy\TermInterface;

class MuzikantAccess {

  public function editAccess(RouteMatchInterface $route_match, AccountInterface $account): AccessResult {
    /** @var TermInterface $term */
    $term = $route_match->getParameter('taxonomy_term');

    if (!$term instanceof TermInterface || $term->bundle() !== 'muzikant') {
      return AccessResult::forbidden();
    }

    // Admin môže editovať vždy
    if ($account->hasPermission('administer taxonomy')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    // Vlastník (claim) môže editovať
    $owner_id = $term->get('field_user')->target_id;
    if ($owner_id && (int) $owner_id === (int) $account->id()) {
      return AccessResult::allowed()->cachePerUser()->addCacheableDependency($term);
    }

    return AccessResult::forbidden()->cachePerUser()->addCacheableDependency($term);
  }

}
