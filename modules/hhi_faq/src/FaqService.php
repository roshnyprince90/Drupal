<?php
/**
 * @file
 * Contains Drupal\hhi_faq\FaqService.
 */

namespace Drupal\hhi_faq;

use Drupal\Core\Entity\EntityRepository;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Language\LanguageManager;
use Drupal\node\NodeInterface;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Class FaqService
 *
 * @package Drupal\hhi_faq
 */
class FaqService {

  /**
   * Current language id
   *
   * @var string
   */
  protected $language;

  /**
   * Module path
   *
   * @var string
   */
  protected $module_path;

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * @var \Drupal\Core\Entity\EntityRepository
   */
  protected $entityRepository;

  /**
   * FaqService constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   * @param \Drupal\Core\Extension\ModuleHandler $module_handler
   * @param \Drupal\Core\Language\LanguageManager $language_manager
   * @param \Drupal\Core\Entity\EntityRepository $entity_repo
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    ModuleHandler $module_handler,
    LanguageManager $language_manager,
    EntityRepository $entity_repo) {

    // Entity Repository
    $this->entityRepository = $entity_repo;

    // Store current language
    $this->language = $language_manager->getCurrentLanguage()->getId();

    // Module path
    $this->module_path = $module_handler->getModule('hhi_faq')->getPath();

    // Load Configuration
    $this->configFactory = $config_factory;
  }

  /**
   * Get translations
   *
   * @param array $entities
   */
  private function getTranslations(&$entities) {
    if (!empty($this->language)) {
      foreach ($entities AS $id => &$entity) {
        // Load proper translation
        $this->getTranslation($entity);
      }
    }
  }

  /**
   * Get translation
   *
   * @param object $entity
   */
  private function getTranslation(&$entity) {
    if (!empty($this->language)) {
      if ($entity->hasTranslation($this->language)) {
        $entity = $this->entityRepository->getTranslationFromContext($entity, $this->language);
      }
    }
  }

  /**
   * Get faq categories.
   *
   * @param string $vocabulary
   *
   * @return array
   */
  public function getFaqCategories($vocabulary = 'faq_categories') {

    $query = \Drupal::entityQuery('taxonomy_term');
    $query->condition('vid', $vocabulary);

    $tids = $query->execute();
    $terms = Term::loadMultiple($tids);

    $categories = [];
    foreach ($terms AS $term) {

      // Get translation
      $this->getTranslation($term);

      if (!empty($term)) {
        $categories[$term->id()] = $term->getName();
      }
    }

    return $categories;
  }

  /**
   * Get faq questions/answers.
   *
   * @param string $search_query
   * @param int $search_category
   * @param int $page
   * @param int $limit
   * @param bool $row_count
   *
   * @return array|int
   */
  public function getFaqNodes($search_query = NULL, $search_category = NULL, $page = 1, $limit = 10, $row_count = FALSE) {

    $query = \Drupal::entityQuery('node');
    $query->condition('type', 'faq');
    $query->condition('status', NodeInterface::PUBLISHED);
    $query->latestRevision();

    // Filter by query value.
    if (!empty($search_query)) {
      $search_query_group = $query->orConditionGroup();
      $search_query_group->condition('title', $search_query, 'CONTAINS');
      $search_query_group->condition('body', $search_query, 'CONTAINS');
      $search_query_group->condition('field_faq_question', $search_query, 'CONTAINS');
      $query->condition($search_query_group);
    }

    // Filter by category
    if ($search_category) {
      $query->condition('field_faq_category', $search_category, '=');
    }

    // Filter by language
    $query->condition('langcode', $this->language, '=');

    // Limit results
    if ($limit) {
      $query->range((($page - 1) * $limit), $limit);
    }

    $query->sort('created' , 'DESC');

    // Run count query
    if ($row_count) {

      $count = $query->count()->execute();
      return $count;

    } else {

      // Load nodes
      $nids = $query->execute();
      $nodes = [];

      $total = count($nids);
      if ($total) {
        $nodes = Node::loadMultiple($nids);
      }

      // Get translations
      $this->getTranslations($nodes);

      return $nodes;
    }
  }
}