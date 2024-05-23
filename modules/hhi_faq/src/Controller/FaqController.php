<?php
/**
 * @file
 * Contains Drupal\hhi_faq\Controller\FaqController.
 */

namespace Drupal\hhi_faq\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Access\AccessResult;
use Drupal\hhi_faq\FaqService;
use Drupal\Core\Cache\CacheableMetadata;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class FaqController
 *
 * @package Drupal\hhi_faq\Controller
 */
class FaqController extends ControllerBase {

  /**
   * @var \Drupal\hhi_faq\FaqService
   */
  protected $faqService;

  /**
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * FaqController constructor.
   *
   * @param \Drupal\hhi_faq\FaqService $faq_service
   * @param \Drupal\Core\Render\Renderer $renderer
   */
  public function __construct(
    FaqService $faq_service,
    Renderer $renderer) {
    $this->faqService = $faq_service;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('hhi_faq.service'),
      $container->get('renderer')
    );
  }

  /**
   * Check access.
   *
   * @return \Drupal\Core\Access\AccessResult
   */
  public function access() {
    return AccessResult::allowed();
  }

  /**
   * Load more FAQ's.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   * @param int $page
   * @param int $limit
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   * @throws \Exception
   */
  public function loadMoreFaqs(Request $request, $page = 0, $limit = 10) {

    $response = [
      'faqs' => NULL,
      'next_page' => NULL,
      'total' => NULL,
      'total_pages' => NULL,
    ];

    $session = $request->getSession();
    if ($session) {

      $search_query = $session->get('faq_query');
      $search_category = $session->get('faq_category');

      $build = [
        '#theme' => 'hhi_faq_search_results_page',
        '#page' => $page,
        '#results' => [],
      ];

      $total_nodes = $this->faqService->getFaqNodes($search_query, $search_category, $page, 0, TRUE);
      $nodes = $this->faqService->getFaqNodes($search_query, $search_category, $page, $limit, FALSE);
      $total_pages = round($total_nodes / $limit);

      foreach ($nodes AS $node) {

        /** @var \Drupal\node\Entity\Node $node */
        $build['#results'][$node->id()] = [
          '#theme' => 'hhi_faq',
          '#id' => $node->id(),
          '#question' => ($node->hasField('field_faq_question')) ? $node->get('field_faq_question')->value : NULL,
          '#answer' => ($node->hasField('body')) ? $node->get('body')->value : NULL,
        ];
      }

      $cacheableMetadata = new CacheableMetadata();
      $cacheableMetadata->addCacheContexts(['session']);
      $cacheableMetadata->addCacheTags(['hhi_faq']);
      $cacheableMetadata->addCacheableDependency($nodes);
      $cacheableMetadata->applyTo($build);

      $response['total'] = $total_nodes;
      $response['next_page'] = $page + 1;
      $response['total_pages'] = $total_pages;
      $response['faqs'] = $this->renderer->render($build);
    }

    return new JsonResponse($response);
  }

  /**
   * Load FAQ's By Selected Category.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   * @param int $page
   * @param int $limit
   * @param int $category
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   * @throws \Exception
   */
  public function loadFaqsByCategory(Request $request, $page = 1, $limit = 10, $category = 0) {

    $response = [
      'faqs' => NULL,
      'current_page' => $page,
      'next_page' => NULL,
      'total' => NULL,
      'total_pages' => NULL,
    ];

    $session = $request->getSession();
    if ($session) {

      $search_query = $session->get('faq_query');
      $session->set('faq_category', $category);
      $search_category = $category;

      $build = [
        '#theme' => 'hhi_faq_search_results_page',
        '#page' => $page,
        '#results' => [],
      ];

      $total_nodes = $this->faqService->getFaqNodes($search_query, $search_category, $page, 0, TRUE);
      $nodes = $this->faqService->getFaqNodes($search_query, $search_category, $page, $limit, FALSE);
      $total_pages = round($total_nodes / $limit);

      foreach ($nodes AS $node) {

        /** @var \Drupal\node\Entity\Node $node */
        $build['#results'][$node->id()] = [
          '#theme' => 'hhi_faq',
          '#id' => $node->id(),
          '#question' => ($node->hasField('field_faq_question')) ? $node->get('field_faq_question')->value : NULL,
          '#answer' => ($node->hasField('body')) ? $node->get('body')->value : NULL,
        ];
      }

      $cacheableMetadata = new CacheableMetadata();
      $cacheableMetadata->addCacheContexts(['session']);
      $cacheableMetadata->addCacheTags(['hhi_faq']);
      $cacheableMetadata->addCacheableDependency($nodes);
      $cacheableMetadata->applyTo($build);

      $response['total'] = $total_nodes;
      $response['next_page'] = $page + 1;
      $response['total_pages'] = $total_pages;
      $response['faqs'] = $this->renderer->render($build);
    }

    return new JsonResponse($response);
  }
}