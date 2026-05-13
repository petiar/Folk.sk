<?php

namespace Drupal\folk_blocks\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[Block(
  id: 'folk_related_articles',
  admin_label: new TranslatableMarkup('Súvisiace články'),
  category: new TranslatableMarkup('Folk.sk'),
)]
class RelatedArticlesBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected RouteMatchInterface $routeMatch,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('entity_type.manager'),
    );
  }

  public function build(): array {
    $node = $this->routeMatch->getParameter('node');

    // Blok sa zobrazí iba na node stránkach s polom field_suvisiaci_clanok
    if (!$node || !$node->hasField('field_suvisiaci_clanok')) {
      return [];
    }

    $referenced = $node->get('field_suvisiaci_clanok')->referencedEntities();

    // Filtruj len publikované
    $related = array_filter($referenced, fn($n) => $n->isPublished());

    if (empty($related)) {
      return [];
    }

    $items = [];
    foreach ($related as $related_node) {
      $items[] = [
        'title' => $related_node->label(),
        'url'   => $related_node->toUrl()->toString(),
        'date'  => $related_node->getCreatedTime(),
        'type'  => $related_node->bundle(),
      ];
    }

    return [
      '#theme'    => 'folk_related_articles',
      '#items'    => $items,
      '#cache'    => [
        'tags'     => Cache::mergeTags($node->getCacheTags(), ['node_list']),
        'contexts' => ['route'],
      ],
    ];
  }

  public function getCacheContexts(): array {
    return Cache::mergeContexts(parent::getCacheContexts(), ['route']);
  }

  public function getCacheTags(): array {
    $node = $this->routeMatch->getParameter('node');
    if ($node) {
      return Cache::mergeTags(parent::getCacheTags(), $node->getCacheTags());
    }
    return parent::getCacheTags();
  }

}
