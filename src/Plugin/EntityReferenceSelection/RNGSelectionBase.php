<?php

/**
 * @file
 * Contains \Drupal\rng\Plugin\EntityReferenceSelection\RNGSelectionBase.
 */

namespace Drupal\rng\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Plugin\EntityReferenceSelection\SelectionBase;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Database\Connection;
use Drupal\rng\EventManagerInterface;
use Drupal\Core\Condition\ConditionManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use \Drupal\Core\Entity\Query\QueryInterface;

/**
 * Base RNG selection plugin.
 */
class RNGSelectionBase extends SelectionBase {

  /**
   * The condition plugin manager.
   *
   * @var \Drupal\Core\Condition\ConditionManager
   */
  protected $conditionManager;

  /**
   * Constructs a new RegisterIdentityContactSelection object.
   *
   * {@inheritdoc}
   *
   * @param \Drupal\rng\EventManagerInterface $event_manager
   *   The RNG event manager.
   * @param \Drupal\Core\Condition\ConditionManager $condition_manager
   *   The condition plugin manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityManagerInterface $entity_manager, ModuleHandlerInterface $module_handler, AccountInterface $current_user, Connection $connection, EventManagerInterface $event_manager, ConditionManager $condition_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_manager, $module_handler, $current_user, $connection);

    if (!isset($this->configuration['handler_settings']['event'])) {
      throw new \Exception('RNG selection handler requires event context.');
    }

    $this->conditionManager = $condition_manager;
    $this->entityType = $this->entityManager->getDefinition($this->configuration['target_type']);
    $this->eventMeta = $event_manager->getMeta($this->configuration['handler_settings']['event']);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity.manager'),
      $container->get('module_handler'),
      $container->get('current_user'),
      $container->get('database'),
      $container->get('rng.event_manager'),
      $container->get('plugin.manager.condition')
    );
  }

  /**
   * Removes existing registered identities from the query.
   *
   * @param \Drupal\Core\Entity\Query\QueryInterface $query
   *   The entity query to modify.
   */
  protected function removeDuplicateRegistrants(QueryInterface &$query) {
    if (!$this->eventMeta->duplicateRegistrantsAllowed()) {
      $entity_ids = [];

      $registrants = $this->eventMeta->getRegistrants($this->entityType->id());
      foreach ($registrants as $registrant) {
        $entity_ids[] = $registrant->getIdentityId()['entity_id'];
      }

      if ($entity_ids) {
        $query->condition($this->entityType->getKey('id'), $entity_ids, 'NOT IN');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    $query = parent::buildEntityQuery($match, $match_operator);
    $this->removeDuplicateRegistrants($query);
    return $query;
  }

}
