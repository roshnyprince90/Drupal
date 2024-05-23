<?php

namespace Drupal\hhi_faq\TwigExtension;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\Entity\Node;

/**
 * Class FaqTwigExtension
 *
 * @package Drupal\hhi_faq\TwigExtension
 */
class FaqTwigExtension extends \Twig_Extension {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * FaqTwigExtension constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getFunctions() {
    return [
      new \Twig_SimpleFunction('hhi_render_faq', [$this, 'renderFaq']),
    ];
  }

  /**
   * Render a faq item.
   *
   * @param \Drupal\node\Entity\Node $node
   *
   * @return array
   */
  public function renderFaq(Node $node) {

    $build = [
      '#theme' => 'hhi_faq',
      '#id' => $node->id(),
      '#question' => ($node->hasField('field_faq_question')) ? $node->get('field_faq_question')->value : NULL,
      '#answer' => ($node->hasField('body')) ? $node->get('body')->value : NULL,
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'hhi_faq.twig.extension';
  }
}