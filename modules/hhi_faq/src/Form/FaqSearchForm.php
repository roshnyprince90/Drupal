<?php

namespace Drupal\hhi_faq\Form;

use Drupal\hhi_faq\FaqService;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\node\Entity\Node;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class FaqSearchForm
 *
 * @package Drupal\hhi_faq\Form
 */
class FaqSearchForm extends FormBase {

  /**
   * @var \Drupal\hhi_faq\FaqService
   */
  protected $faqService;

  /**
   * @var bool
   */
  protected $isAjax;

  /**
   * FaqSearchForm constructor.
   *
   * @param \Drupal\hhi_faq\FaqService $faq_service
   */
  public function __construct(FaqService $faq_service) {
    $this->faqService = $faq_service;
    $this->isAjax = TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('hhi_faq.service')
    );
  }

  /**
   * {@inheritdoc}.
   */
  public function getFormId() {
    return 'hhi_faq_search_form';
  }

  /**
   * {@inheritdoc}.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $page = 1, $limit = 10, $is_ajax = TRUE, $faq_dir_page = NULL) {

    $this->isAjax = $is_ajax;

    $form_state->set('limit', $limit);
    $form_state->set('page', $page);
    $form_state->set('faq_dir_page', $faq_dir_page);

    $form['#disable_inline_form_errors'] = FALSE;

    $form['#attributes']['novalidate'] = 'novalidate';
    $form['#attributes']['class'][] = 'c-faq-search-form';

    $form['container'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'faq-search-form-container',
        'class' => 'c-faq-search-form__container',
      ],
    ];

    $session = $this->getRequest()->getSession();

    $search_query = $session->get('faq_query');
    $form['container']['query'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search Query'),
      '#title_display' => 'invisible',
      '#attributes' => [
        'class' => [
          'c-faq-search-form__query',
        ],
        'placeholder' => $this->t('Search'),
      ],
      '#default_value' => $search_query,
    ];

    if ($this->isAjax) {
      $form['container']['query']['#ajax'] = [
        'disable-refocus' => TRUE,
        'callback' => '::ajaxCallback',
        'event' => 'faq_search_query_change',
        'wrapper' => 'faq-search-form-container',
        'progress' => [
          'type' => 'none',
          'message' => NULL,
        ],
      ];
    }

    $form['container']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
      '#attributes' => [
        'class' => [
          'c-faq-search-form__btn',
        ],
      ],
    ];

    if ($this->isAjax) {
      $form['container']['submit']['#ajax'] = [
        'disable-refocus' => TRUE,
        'callback' => '::ajaxCallback',
        'wrapper' => 'faq-search-form-container',
        'progress' => [
          'type' => 'none',
          'message' => NULL,
        ],
      ];
    }

    $form['#attached']['drupalSettings'] = [
      'hhi_faq' => [
        'limit' => $limit,
        'page' => $page,
        'is_ajax' => $is_ajax,
      ],
    ];

    $form['#attached']['library'][] = 'hhi_faq/faq';
    $form['#attached']['library'][] = 'classy/messages';

    // No cache.
    $form_state->setCached(FALSE);

    $cacheableMetadata = new CacheableMetadata();
    $cacheableMetadata->setCacheMaxAge(0);
    $cacheableMetadata->applyTo($form);

    return $form;
  }

  /**
   * AJAX Callback.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array|\Drupal\Core\Ajax\AjaxResponse
   */
  public function ajaxCallback(array &$form, FormStateInterface $form_state) {

    if (!$form_state->getErrors()) {

      $page = !empty($form_state->get('page')) ? $form_state->get('page') : 1;
      $limit = !empty($form_state->get('limit')) ? $form_state->get('limit') : 10;

      $this->submitForm($form, $form_state);

      $build = [
        '#theme' => 'hhi_faq_search_results_page',
        '#page' => $page,
        '#results' => [],
      ];

      $session = $this->getRequest()->getSession();
      $search_query = $session->get('faq_query');
      $search_category = $session->get('faq_category');

      $total_nodes = $this->faqService->getFaqNodes($search_query, $search_category, $page, 0, TRUE);
      $nodes = $this->faqService->getFaqNodes($search_query, $search_category, $page, $limit, FALSE);

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

      $next_page = $page + 1;
      $total_pages = round($total_nodes / $limit);

      $response = new AjaxResponse();
      $response->addCommand(new HtmlCommand('.c-faq__search-results-ajax', $build));
      $response->addCommand(new HtmlCommand('.c-faq__search-results-total', $total_nodes));
      $response->addCommand(new InvokeCommand(NULL, 'faqPager', [$next_page, $total_pages]));

      return $response;

    } else {

      $form_state->setRebuild(TRUE);
      return $form['container'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $query = $form_state->getValue('query');

    $request = $this->getRequest();
    if (!empty($request)) {
      $session = $request->getSession();
      $session->set('faq_query', $query);
    }

    if (!$this->isAjax && !empty($form_state->get('faq_dir_page'))) {
      $faq_dir_page = Node::load($form_state->get('faq_dir_page'));
      if ($faq_dir_page) {
        $url = Url::fromRoute('entity.node.canonical', ['node' => $faq_dir_page->id()]);
        return $form_state->setRedirectUrl($url);
      }
    }
  }

}
