<?php

namespace Drupal\hhi_events\Plugin\Field\FieldFormatter;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\date_recur\Plugin\Field\FieldType\DateRecurItem;
use Drupal\datetime_range\Plugin\Field\FieldFormatter\DateRangeDefaultFormatter;

/**
 * Plugin implementation of the 'event_date_recur_default_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "event_date_recur_default_formatter",
 *   label = @Translation("Event date recur default formatter"),
 *   field_types = {
 *     "date_recur"
 *   }
 * )
 */
class EventDateRecurDefaultFormatter extends DateRangeDefaultFormatter {

  /** @var int */
  protected $occurrenceCounter;

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
        // Implement default settings.
        'show_rrule' => TRUE,
        'show_next' => 1,
        'occurrence_format_type' => 'medium',
        'same_end_date_format_type' => 'medium',
        'count_per_item' => TRUE,
      ] + parent::defaultSettings();
  }

  protected function showNextOptions() {
    // This cannot work for infinite fields.
    // $next_options[-1] = $this->t('All');
    $next_options[0] = $this->t('None');
    for ($i = 1; $i <= 20; $i++) {
      $next_options[$i] = $i;
    }
    return $next_options;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {

    $form = parent::settingsForm($form, $form_state);

    $form['show_rrule'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show repeat rule'),
      '#default_value' => $this->getSetting('show_rrule'),
    ];

    $form['show_next'] = [
      '#type' => 'select',
      '#options' => $this->showNextOptions(),
      '#title' => $this->t('Show next occurrences'),
      '#default_value' => $this->getSetting('show_next'),
    ];

    $form['count_per_item'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Limit occurrences per field item'),
      '#default_value' => $this->getSetting('count_per_item'),
      '#description' => $this->t('If disabled, the number of occurrences shown is limited across all field items.')
    ];

    $form['occurrence_format_type'] = $form['format_type'];
    $form['occurrence_format_type']['#title'] .=  ' ' . t('(Occurrences)');
    $form['occurrence_format_type']['#default_value'] = $this->getSetting('occurrence_format_type');

    $form['same_end_date_format_type'] = $form['format_type'];
    $form['same_end_date_format_type']['#title'] .=  ' ' . t('(End date if same day as start date)');
    $form['same_end_date_format_type']['#default_value'] = $this->getSetting('same_end_date_format_type');

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {

    $summary = parent::settingsSummary();

    $summary[] = $this->t('Show repeat rule') . ': ' . ($this->getSetting('show_rrule') ? $this->t('Yes') : $this->t('No'));
    $summary[] = $this->t('Show next occurrences') . ': ' . $this->showNextOptions()[$this->getSetting('show_next')];

    $date = new DrupalDateTime();
    $date->_dateRecurIsOccurrence = TRUE;

    $summary[] = t('Occurrence format: @display', ['@display' => $this->formatDate($date)]);

    return $summary;
  }

  protected function buildDateRangeValue($start_date, $end_date, $isOccurrence = FALSE) {

    // Protection. @todo: Find out why sometimes a \DateTime arrives.
    if ($start_date instanceof \DateTime) {
      $start_date = DrupalDateTime::createFromDateTime($start_date);
    }

    if ($end_date instanceof \DateTime) {
      $end_date = DrupalDateTime::createFromDateTime($end_date);
    }

    if ($isOccurrence) {
      $start_date->_dateRecurIsOccurrence = $end_date->_dateRecurIsOccurrence = TRUE;
    }

    if ($start_date->format('Ymd') == $end_date->format('Ymd')) {
      $end_date->_same_end_date = TRUE;
    } else {
      $end_date->_same_end_date = FALSE;
    }

    // Default element
    $element = $this->buildDateWithIsoAttribute($start_date);

    // If timestamps are not the same, then show both dates
    if ($start_date->format('U') !== $end_date->format('U')) {

      $element = [
        'start_date' => $this->buildDateWithIsoAttribute($start_date),
        'separator'  => ['#plain_text' => ' ' . $this->getSetting('separator') . ' '],
        'end_date'   => $this->buildDateWithIsoAttribute($end_date),
      ];

      $element['start_date']['#attributes']['itemprop'] = "startDate";
      $element['end_date']['#attributes']['itemprop'] = "endDate";
    }

    // If start date and end date are same, just show start date
    if ($start_date && $end_date && $end_date->_same_end_date) {

      $element = $this->buildDateWithIsoAttribute($start_date);
      $element['#attributes']['itemprop'] = "startDate";
    }

    $cacheableMetadata = new CacheableMetadata();
    $cacheableMetadata->setCacheMaxAge(0);
    $cacheableMetadata->applyTo($element);

    return $element;
  }

  protected function formatDate($date) {

    if (!empty($date->_same_end_date)) {
      $format_type = $this->getSetting('same_end_date_format_type');
    }
    else if (empty($date->_dateRecurIsOccurrence)) {
      $format_type = $this->getSetting('format_type');
    }
    else {
      $format_type = $this->getSetting('occurrence_format_type');
    }

    $timezone = $this->getSetting('timezone_override') ?: $date->getTimezone()->getName();
    return $this->dateFormatter->format($date->getTimestamp(), $format_type, '', $timezone != '' ? $timezone : NULL);
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    $this->occurrenceCounter = 0;
    foreach ($items as $delta => $item) {
      $elements[$delta] = $this->viewValue($item);
    }

    return $elements;
  }

  /**
   * Generate the output appropriate for one field item.
   *
   * @param DateRecurItem $item
   *   One field item.
   *
   * @return string
   *   The textual output generated.
   */
  protected function viewValue(DateRecurItem $item) {

    $build = [
      '#theme' => 'hhi_event_date_recur_default_formatter',
    ];

    if (empty($item->end_date)) {
      $item->end_date = clone $item->start_date;
    }
    $build['#date'] = $this->buildDateRangeValue($item->start_date, $item->end_date);

    if (empty($item->rrule)) {
      $build['#isRecurring'] = FALSE;
    } else {
      $build['#isRecurring'] = TRUE;
    }

    if ($this->getSetting('show_rrule') && !empty($item->rrule)) {
      $build['#repeatrule'] = $item->getOccurrenceHandler()->humanReadable();
    }

    $build['#occurrences'] = $this->viewOccurrences($item);

    if (!empty($item->_attributes)) {
      $build += $item->_attributes;
      // Unset field item attributes since they have been included in the
      // formatter output and should not be rendered in the field template.
      unset($item->_attributes);
    }

    $uid = \Drupal::currentUser()->id();
    $cacheableMetadata = new CacheableMetadata();
    $cacheableMetadata->addCacheContexts(['user']);
    $cacheableMetadata->addCacheTags(['user_event_filters.' . $uid]);
    $cacheableMetadata->setCacheMaxAge(0);
    $cacheableMetadata->applyTo($build);

    return $build;
  }

  protected function viewOccurrences(DateRecurItem $item) {

    $eventsService = \Drupal::service('hhi_events.service');

    $min_date = $eventsService->getMinDate();
    if ( $min_date ) {

      $min_date = strtotime($min_date);
      $min_datetime = mktime(0, 0, 0, date('n', $min_date), date('j', $min_date), date('Y', $min_date));

      $min_date = new \DateTime();
      $min_date->setTimestamp($min_datetime);
    }

    $max_date = $eventsService->getMaxDate();
    if ( $max_date ) {

      $max_date = strtotime($max_date);
      $max_datetime = mktime(23, 59, 59, date('n', $max_date), date('j', $max_date), date('Y', $max_date));

      $max_date = new \DateTime();
      $max_date->setTimestamp($max_datetime);
    }

    $build = [];

    $count = $this->getSetting('show_next');
    if ( !$this->getSetting('count_per_item') ) {
      $count = $count - $this->occurrenceCounter;
    }

    if ( $count > 0 ) {

      $occurrences = [];

      if ($min_date || $max_date) {

        if ($min_date && !$max_date) {
          $occurrences = $item->getOccurrenceHandler()->getOccurrencesForDisplay($min_date, NULL, $count);
        } elseif ($min_date && $max_date) {
          $occurrences = $item->getOccurrenceHandler()->getOccurrencesForDisplay($min_date, $max_date, $count);
        }

      } else {

        $start_time = mktime(0, 0, 0);
        $start = new \DateTime();
        $start->setTimestamp($start_time);

        $occurrences = $item->getOccurrenceHandler()->getOccurrencesForDisplay($start, NULL, $count);
      }

      foreach ($occurrences as $occurrence) {
        if ( !empty($occurrence['value']) ) {
          $build[] = $this->buildDateRangeValue($occurrence['value'], $occurrence['end_value'], TRUE);
          break;
        }
      }

      $this->occurrenceCounter += count($occurrences);
    }

    return $build;
  }
}
