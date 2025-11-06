<?php

namespace Drupal\simple_metatag\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for path-based metatag overrides list.
 */
class PathMetatagsController extends ControllerBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a PathMetatagsController object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database')
    );
  }

  /**
   * Lists all path-based metatag overrides.
   *
   * @return array
   *   A render array.
   */
  public function listOverrides() {
    $build = [];

    $build['add_link'] = [
      '#type' => 'link',
      '#title' => $this->t('Add new override'),
      '#url' => Url::fromRoute('simple_metatag.path_metatags_add'),
      '#attributes' => [
        'class' => ['button', 'button--primary', 'button--small'],
      ],
    ];

    $header = [
      $this->t('Path'),
      $this->t('Title'),
      $this->t('Description'),
      $this->t('Domain'),
      $this->t('Language'),
      $this->t('Operations'),
    ];

    $rows = [];

    $query = $this->database->select('simple_metatag_path', 'sm')
      ->fields('sm')
      ->orderBy('id', 'ASC');

    $results = $query->execute()->fetchAll();

    foreach ($results as $override) {
      // Format description (truncate to 80 chars).
      $description = $override->description;
      if (!empty($description) && mb_strlen($description) > 80) {
        $description = mb_substr($description, 0, 80) . '...';
      }

      // Format domains.
      $domain_display = $this->t('All');
      if (!empty($override->domains)) {
        $domains = unserialize($override->domains);
        if (is_array($domains) && !empty($domains)) {
          $domain_display = implode(', ', $domains);
        }
      }

      $rows[] = [
        $override->path,
        $override->title,
        $description ?: '-',
        $domain_display,
        $override->language ?: $this->t('All'),
        [
          'data' => [
            '#type' => 'operations',
            '#links' => [
              'edit' => [
                'title' => $this->t('Edit'),
                'url' => Url::fromRoute('simple_metatag.path_metatags_edit', ['id' => $override->id]),
              ],
              'delete' => [
                'title' => $this->t('Delete'),
                'url' => Url::fromRoute('simple_metatag.path_metatags_delete', ['id' => $override->id]),
              ],
            ],
          ],
        ],
      ];
    }

    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No path-based metatag overrides found. <a href="@add">Add one now</a>.', [
        '@add' => Url::fromRoute('simple_metatag.path_metatags_add')->toString(),
      ]),
    ];

    return $build;
  }

  /**
   * Delete a path-based metatag override.
   *
   * @param int $id
   *   The override ID.
   *
   * @return array
   *   A render array.
   */
  public function deleteOverride($id) {
    $this->database->delete('simple_metatag_path')
      ->condition('id', $id)
      ->execute();

    $this->messenger()->addStatus($this->t('Path-based metatag override deleted.'));

    return $this->redirect('simple_metatag.path_metatags_list');
  }

}
