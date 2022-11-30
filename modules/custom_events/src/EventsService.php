<?php
/**
 * @file
 * Contains Drupal\custom_events\EventsService.
 */

namespace Drupal\custom_events;

use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Path\PathMatcher;
use Drupal\node\Entity\Node;
use Drupal\user\PrivateTempStoreFactory;
use Drupal\Core\Session\SessionManager;
use Drupal\Core\Entity\EntityRepository;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DrupalDateTime;

class EventsService {

  /**
   * The path matcher.
   *
   * @var \Drupal\Core\Path\PathMatcherInterface
   */
  protected $pathMatcher;

  /**
   * Private/temporary storage
   *
   * @var \Drupal\user\PrivateTempStoreFactory
   */
  protected $tempStore;

  /**
   * Drupal\Core\Session\SessionManager definition.
   *
   * @var \Drupal\Core\Session\SessionManager
   */
  protected $sessionManager;

  /**
   * @var \Drupal\Core\Entity\EntityRepository
   */
  protected $entityRepository;

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * @var \Drupal\Core\Database\Connection
   */
  public $database;


  /**
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * EventsService constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   * @param \Drupal\Core\Extension\ModuleHandler $module_handler
   * @param \Drupal\Core\Path\PathMatcher $path_matcher
   * @param \Drupal\user\PrivateTempStoreFactory $temp_store_factory
   * @param \Drupal\Core\Session\SessionManager $session_manager
   * @param \Drupal\Core\Entity\EntityRepository $entity_repo
   * @param \Drupal\Core\Database\Connection $database
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    ModuleHandler $module_handler,
    PathMatcher $path_matcher,
    PrivateTempStoreFactory $temp_store_factory,
    SessionManager $session_manager,
    EntityRepository $entity_repo,
    Connection $database,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    $this->sessionManager = $session_manager;
    $this->entityRepository = $entity_repo;
    $this->tempStore = $temp_store_factory->get('custom_events');
    $this->configFactory = $config_factory;
    $this->config = $this->configFactory->get('custom_events.settings');
    $this->pathMatcher = $path_matcher;
    $this->database = $database;
    $this->loggerFactory = $logger_factory->get('custom_events');
  }


  /**
   * Unpublishes events that have passed the end date
   */
  public function unpublishOldEvents() {
    $query = \Drupal::entityQuery('node')
                   ->condition('type', 'event')
                   ->condition('status', 1) 
                   ->condition('field_event_date.end_value', date(DATE_ATOM, strtotime('-1 day')), '<');
    $nids  = $query->execute();
    foreach ($nids as $nid) {
      $node = Node::load($nid);
      $node_title = $node->getTitle();
      if (!empty($node)) {
        $node->setUnpublished()->save();
        $this->loggerFactory->info('The Event '.$node_title.' with Node ID '.$node->id().' was unpublished.');
      }
    }

  }

 /**
  * Delete oudated event nodes older than 1 year
  */
  public function deleteOldEvents() {
    $query = \Drupal::entityQuery('node')
                   ->condition('type', 'event')
                   ->condition('field_event_date.end_value', date(DATE_ATOM, strtotime('-1 year')), '<')
                   ->accessCheck(FALSE); 
    $nids  = $query->execute();
    foreach ($nids as $nid) {
      $node = Node::load($nid);
      $node_title = $node->getTitle();
      if (!empty($node)) {
        $this->loggerFactory->info('Outdated Event  '.$node_title.' with Node ID '.$node->id().' was deleted.');
        $node->delete();
      }
    }
  }

}
