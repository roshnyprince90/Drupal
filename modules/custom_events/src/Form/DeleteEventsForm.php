<?php

namespace Drupal\custom_events\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\custom_events\EventsService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a confirmation form to confirm deletion of inactive events
 */
class DeleteEventsForm extends ConfirmFormBase
{

  /**
   * @var \Drupal\custom_events\EventsService
   */
  protected $eventsService;

  /**
   * DeleteEventsForm constructor.
   *
   * @param \Drupal\custom_events\EventsService $events_service
   */
  public function __construct(EventsService $events_service) {
    $this->eventsService = $events_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('custom_events.service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return "delete_events_form";
  }

  /**
   * {@inheritdoc}
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
   $this->eventsService->deleteOldEvents();
   $this->messenger()->addStatus($this->t('Inactive events have been deleted.'));
  }

    /**
   * Returns the question to ask the user.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The form question. The page title will be set to this value.
   */
  public function getQuestion() {
    return $this->t('Do you want to delete inactive events?');
  }

    /**
   * Returns the route to go to if the user cancels the action.
   *
   * @return \Drupal\Core\Url
   *   A URL object.
   */
  public function getCancelUrl(){
    return new Url('system.admin_content');
  }

 
}
