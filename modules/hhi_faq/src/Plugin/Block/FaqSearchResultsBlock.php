<?php

namespace Drupal\hhi_faq\Plugin\Block;

use Drupal\hhi_faq\FaqService;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides FAQ Search Results Block.
 *
 * @Block(
 *   id          = "hhi_faq_search_results_block",
 *   admin_label = @Translation("FAQ Search Results"),
 *   category    = @Translation("HHI"),
 * )
 */
class FaqSearchResultsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\hhi_faq\FaqService
   */
  protected $faqService;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $request;

  /**
   * FaqSearchResultsBlock constructor.
   *
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   * @param \Drupal\hhi_faq\FaqService $faq_service
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    FaqService $faq_service,
    RequestStack $request_stack
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->faqService = $faq_service;
    $this->request = $request_stack->getCurrentRequest();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('hhi_faq.service'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'limit' => 10,
      'page' => 1,
      'btn_label' => 'Load More',
      'show_categories' => TRUE,
      'active_category' => 0, // 0 => All Categories
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {

    $form['limit'] = [
      '#type' => 'number',
      '#min' => 5,
      '#title' => $this->t('Number of results to show per page.'),
      '#default_value' => $this->configuration['limit'],
      '#required' => TRUE,
    ];

    $form['page'] = [
      '#type' => 'number',
      '#min' => 1,
      '#title' => $this->t('Page of results to start on. Defaults to first page.'),
      '#default_value' => $this->configuration['page'],
      '#required' => TRUE,
    ];

    $form['btn_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Load more button label.'),
      '#default_value' => $this->configuration['btn_label'],
    ];

    $form['show_categories'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show FAQ categories.'),
      '#default_value' => $this->configuration['show_categories'],
    ];

    $categories = [
      0 => $this->t('All Categories'),
    ];
    $categories += $this->faqService->getFaqCategories();
    $form['active_category'] = [
      '#type' => 'select',
      '#title' => $this->t('Active Category'),
      '#options' => $categories,
      '#default_value' => $this->configuration['active_category'],
    ];


    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    if ($form_state->hasAnyErrors()) {
      return;
    }
    else {
      $this->configuration['limit'] = $form_state->getValue('limit');
      $this->configuration['page'] = $form_state->getValue('page');
      $this->configuration['btn_label'] = $form_state->getValue('btn_label');
      $this->configuration['show_categories'] = $form_state->getValue('show_categories');
      $this->configuration['active_category'] = $form_state->getValue('active_category');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function build() {

    $page = $this->configuration['page'];
    $limit = $this->configuration['limit'];
    $btn_label = $this->configuration['btn_label'];
    $show_categories = $this->configuration['show_categories'];

    $results_build = [
      '#theme' => 'hhi_faq_search_results_page',
      '#page' => $page,
      '#results' => [],
    ];

    $session = $this->request->getSession();
    $search_query = $session->get('faq_query');
    $search_category = $session->get('faq_category');

    if (!empty($this->configuration['active_category'])) {
      $search_category = $this->configuration['active_category'];
    }

    // Results
    $total_nodes = $this->faqService->getFaqNodes($search_query, $search_category, $page, 0, TRUE);
    $nodes = $this->faqService->getFaqNodes($search_query, $search_category, $page, $limit, FALSE);

    foreach ($nodes AS $node) {

      /** @var \Drupal\node\Entity\Node $node */
      $results_build['#results'][$node->id()] = [
        '#theme' => 'hhi_faq',
        '#id' => $node->id(),
        '#question' => ($node->hasField('field_faq_question')) ? $node->get('field_faq_question')->value : NULL,
        '#answer' => ($node->hasField('body')) ? $node->get('body')->value : NULL,
      ];
    }

    // Pager
    $pager_build = [
      '#theme' => 'hhi_faq_search_pager',
      '#total_pages' => round($total_nodes / $limit),
      '#page' => $page + 1,
      '#limit' => $limit,
      '#btn_label' => $btn_label,
    ];

    // Categories
    $categories_build = [
      '#theme' => 'hhi_faq_categories',
      '#categories' => $this->faqService->getFaqCategories(),
      '#active_category' => $search_category,
    ];

    $build = [
      '#theme' => 'hhi_faq_search_results',
      '#results' => $results_build,
      '#total' => $total_nodes,
      '#pager' => $pager_build,
      '#categories' => $categories_build,
      '#show_categories' => $show_categories,
    ];

    $cacheableMetadata = new CacheableMetadata();
    $cacheableMetadata->addCacheContexts(['session']);
    $cacheableMetadata->addCacheTags(['hhi_faq']);
    $cacheableMetadata->addCacheableDependency($this->configuration);
    $cacheableMetadata->addCacheableDependency($nodes);
    $cacheableMetadata->applyTo($build);

    return $build;
  }

}
