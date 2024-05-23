<?php

namespace Drupal\hhi_faq\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilder;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides FAQ Search Form Block.
 *
 * @Block(
 *   id          = "hhi_faq_search_form_block",
 *   admin_label = @Translation("FAQ Search Form"),
 *   category    = @Translation("HHI"),
 * )
 */
class FaqSearchFormBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Drupal\Core\Form\FormBuilder definition.
   *
   * @var \Drupal\Core\Form\FormBuilder
   */
  protected $formBuilder;

  /**
   * FaqSearchFormBlock constructor.
   *
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   * @param \Drupal\Core\Form\FormBuilder $form_builder
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    FormBuilder $form_builder
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'limit' => 10,
      'page' => 1,
      'is_ajax' => TRUE,
      'faq_dir_page' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {

    $form['is_ajax'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use AJAX'),
      '#default_value' => $this->configuration['is_ajax'],
    ];

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

    $faq_dir_page = NULL;
    if (!empty($this->configuration['faq_dir_page'])) {
      $faq_dir_page = Node::load($this->configuration['faq_dir_page']);
    }

    $form['faq_dir_page'] = [
      '#title' => $this->t('FAQ Directory Page'),
      '#type' => 'entity_autocomplete',
      '#target_type' => 'node',
      '#selection_settings' => [
        'target_bundles' => ['page'],
      ],
      '#default_value' => $faq_dir_page,
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
      $this->configuration['is_ajax'] = $form_state->getValue('is_ajax');
      $this->configuration['limit'] = $form_state->getValue('limit');
      $this->configuration['page'] = $form_state->getValue('page');
      $this->configuration['faq_dir_page'] = $form_state->getValue('faq_dir_page');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $is_ajax = $this->configuration['is_ajax'];
    $page = $this->configuration['page'];
    $limit = $this->configuration['limit'];
    $faq_dir_page = $this->configuration['faq_dir_page'];

    return $this->formBuilder->getForm('Drupal\hhi_faq\Form\FaqSearchForm', $page, $limit, $is_ajax, $faq_dir_page);
  }

}
