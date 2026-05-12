<?php

namespace Drupal\folk_submit\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

#[Block(
  id: 'folk_submit_article_block',
  admin_label: new TranslatableMarkup('Pridať článok — výzva'),
  category: new TranslatableMarkup('Folk.sk'),
)]
class SubmitArticleBlock extends BlockBase {

  public function build(): array {
    return [
      '#theme'       => 'folk_submit_block',
      '#description' => $this->t('Boli ste na koncerte? Na festivale? Počuli ste dobrý album? Napíšte o tom všetkým!'),
      '#url'         => Url::fromRoute('folk_submit.form')->toString(),
      '#event_url'   => Url::fromRoute('folk_submit.event_form')->toString(),
    ];
  }

}
