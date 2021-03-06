<?php

namespace Drupal\Tests\datetime\Kernel\Views;

use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\views\Views;

/**
 * Tests date-only fields.
 *
 * @group datetime
 */
class FilterDateTest extends DateTimeHandlerTestBase {

  /**
   * {@inheritdoc}
   */
  public static $testViews = ['test_filter_datetime'];

  /**
   * For offset tests, set to the current time.
   */
  protected static $date;

  /**
   * {@inheritdoc}
   *
   * Create nodes with relative dates of yesterday, today, and tomorrow.
   */
  protected function setUp($import_test_views = TRUE) {
    parent::setUp($import_test_views);

    // Set to 'today'.
    static::$date = REQUEST_TIME;

    // Change field storage to date-only.
    $storage = FieldStorageConfig::load('node.' . static::$field_name);
    $storage->setSetting('datetime_type', DateTimeItem::DATETIME_TYPE_DATE);
    $storage->save();

    $dates = [
      // Tomorrow.
      \Drupal::service('date.formatter')->format(static::$date + 86400, 'custom', DateTimeItemInterface::DATE_STORAGE_FORMAT, DateTimeItemInterface::STORAGE_TIMEZONE),
      // Today.
      \Drupal::service('date.formatter')->format(static::$date, 'custom', DateTimeItemInterface::DATE_STORAGE_FORMAT, DateTimeItemInterface::STORAGE_TIMEZONE),
      // Yesterday.
      \Drupal::service('date.formatter')->format(static::$date - 86400, 'custom', DateTimeItemInterface::DATE_STORAGE_FORMAT, DateTimeItemInterface::STORAGE_TIMEZONE),
    ];

    foreach ($dates as $date) {
      $node = Node::create([
        'title' => $this->randomMachineName(8),
        'type' => 'page',
        'field_date' => [
          'value' => $date,
        ]
      ]);
      $node->save();
      $this->nodes[] = $node;
    }
  }

  /**
   * Test offsets with date-only fields.
   */
  public function testDateOffsets() {
    $view = Views::getView('test_filter_datetime');
    $field = static::$field_name . '_value';

    // Test simple operations.
    $view->initHandlers();

    // A greater than or equal to 'now', should return the 'today' and
    // the 'tomorrow' node.
    $view->filter[$field]->operator = '>=';
    $view->filter[$field]->value['type'] = 'offset';
    $view->filter[$field]->value['value'] = 'now';
    $view->setDisplay('default');
    $this->executeView($view);
    $expected_result = [
      ['nid' => $this->nodes[0]->id()],
      ['nid' => $this->nodes[1]->id()],
    ];
    $this->assertIdenticalResultset($view, $expected_result, $this->map);
    $view->destroy();

    // Only dates in the past.
    $view->initHandlers();
    $view->filter[$field]->operator = '<';
    $view->filter[$field]->value['type'] = 'offset';
    $view->filter[$field]->value['value'] = 'now';
    $view->setDisplay('default');
    $this->executeView($view);
    $expected_result = [
      ['nid' => $this->nodes[2]->id()],
    ];
    $this->assertIdenticalResultset($view, $expected_result, $this->map);
    $view->destroy();

    // Test offset for between operator. Only the 'tomorrow' node should appear.
    $view->initHandlers();
    $view->filter[$field]->operator = 'between';
    $view->filter[$field]->value['type'] = 'offset';
    $view->filter[$field]->value['max'] = '+2 days';
    $view->filter[$field]->value['min'] = '+1 day';
    $view->setDisplay('default');
    $this->executeView($view);
    $expected_result = [
      ['nid' => $this->nodes[0]->id()],
    ];
    $this->assertIdenticalResultset($view, $expected_result, $this->map);
  }

}
