<?php

namespace Drupal\simple_metatag;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Service for generating metatags.
 */
class MetatagGenerator {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The current path.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected $currentPath;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs a MetatagGenerator object.
   */
  public function __construct(
    Connection $database,
    EntityTypeManagerInterface $entity_type_manager,
    RouteMatchInterface $route_match,
    CurrentPathStack $current_path,
    LanguageManagerInterface $language_manager
  ) {
    $this->database = $database;
    $this->entityTypeManager = $entity_type_manager;
    $this->routeMatch = $route_match;
    $this->currentPath = $current_path;
    $this->languageManager = $language_manager;
  }

  /**
   * Generate metatags for the current page.
   *
   * @return array
   *   An array of metatags.
   */
  public function generateMetatags() {
    $metatags = [];

    // First, check for path-based overrides.
    $path_override = $this->getPathOverride();
    if ($path_override) {
      return $this->buildMetatagsFromOverride($path_override);
    }

    // Then check for entity-based metatags.
    $node = $this->routeMatch->getParameter('node');
    $term = $this->routeMatch->getParameter('taxonomy_term');

    if ($node instanceof NodeInterface) {
      $metatags = $this->generateNodeMetatags($node);
    }
    elseif ($term instanceof TermInterface) {
      $metatags = $this->generateTermMetatags($term);
    }

    return $metatags;
  }

  /**
   * Get path-based override for the current page.
   *
   * @return array|null
   *   The override data or NULL if not found.
   */
  protected function getPathOverride() {
    $current_path = $this->currentPath->getPath();
    $current_language = $this->languageManager->getCurrentLanguage()->getId();
    $current_domain = \Drupal::request()->getHost();

    // Check for <front> pattern.
    $route_name = $this->routeMatch->getRouteName();
    if ($route_name === 'system.admin') {
      $current_path = '<front>';
    }

    $query = $this->database->select('simple_metatag_path', 'sm')
      ->fields('sm')
      ->condition('status', 1)
      ->orderBy('weight', 'DESC');

    $results = $query->execute()->fetchAll();

    foreach ($results as $override) {
      // Check path match.
      $pattern = $override->path;
      $path_matches = $this->pathMatches($current_path, $pattern);

      if (!$path_matches) {
        continue;
      }

      // Check language match.
      if (!empty($override->language) && $override->language !== $current_language) {
        continue;
      }

      // Check domain match.
      if (!empty($override->domains)) {
        $domains = unserialize($override->domains);
        if (!in_array($current_domain, $domains)) {
          continue;
        }
      }

      // Found a match.
      return (array) $override;
    }

    return NULL;
  }

  /**
   * Check if a path matches a pattern.
   *
   * @param string $path
   *   The path to check.
   * @param string $pattern
   *   The pattern to match against.
   *
   * @return bool
   *   TRUE if the path matches the pattern.
   */
  protected function pathMatches($path, $pattern) {
    if ($path === $pattern) {
      return TRUE;
    }

    // Convert wildcard pattern to regex.
    $pattern = preg_quote($pattern, '/');
    $pattern = str_replace('\*', '.*', $pattern);
    $pattern = '/^' . $pattern . '$/';

    return (bool) preg_match($pattern, $path);
  }

  /**
   * Build metatags from path override.
   *
   * @param array $override
   *   The override data.
   *
   * @return array
   *   The metatags.
   */
  protected function buildMetatagsFromOverride(array $override) {
    global $base_url;
    $metatags = [];

    if (!empty($override['title'])) {
      $metatags['title'] = $override['title'];
      $metatags['og:title'] = $override['title'];
    }

    if (!empty($override['description'])) {
      $metatags['description'] = $override['description'];
      $metatags['og:description'] = $override['description'];
    }

    if (!empty($override['image'])) {
      // Image is stored as file ID.
      $file = File::load($override['image']);
      if ($file) {
        $metatags['og:image'] = $this->getAbsoluteFileUrl($file);
      }
    }

    $metatags['og:url'] = $base_url . $this->currentPath->getPath();

    return $metatags;
  }

  /**
   * Generate metatags for a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   *
   * @return array
   *   The metatags.
   */
  protected function generateNodeMetatags(NodeInterface $node) {
    global $base_url;
    $metatags = [];

    // Load metatag data from database.
    $metatag_data = $this->database->select('simple_metatag_entity', 'e')
      ->fields('e')
      ->condition('entity_type', 'node')
      ->condition('entity_id', $node->id())
      ->execute()
      ->fetchAssoc();

    // Title.
    if (!empty($metatag_data['title'])) {
      $title = $metatag_data['title'];
    }
    else {
      $title = $node->getTitle();
    }
    $metatags['title'] = $title;
    $metatags['og:title'] = $title;

    // Description.
    if (!empty($metatag_data['description'])) {
      $description = $metatag_data['description'];
    }
    else {
      $description = $this->generateDescriptionFromBody($node);
    }
    if (!empty($description)) {
      $metatags['description'] = $description;
      $metatags['og:description'] = $description;
    }

    // Image.
    $image_url = $this->getNodeImageUrl($node, $metatag_data);
    if ($image_url) {
      $metatags['og:image'] = $image_url;
    }

    // URL.
    $metatags['og:url'] = $node->toUrl('canonical', ['absolute' => TRUE])->toString();

    return $metatags;
  }

  /**
   * Generate metatags for a taxonomy term.
   *
   * @param \Drupal\taxonomy\TermInterface $term
   *   The taxonomy term entity.
   *
   * @return array
   *   The metatags.
   */
  protected function generateTermMetatags(TermInterface $term) {
    global $base_url;
    $metatags = [];

    // Load metatag data from database.
    $metatag_data = $this->database->select('simple_metatag_entity', 'e')
      ->fields('e')
      ->condition('entity_type', 'taxonomy_term')
      ->condition('entity_id', $term->id())
      ->execute()
      ->fetchAssoc();

    // Title.
    if (!empty($metatag_data['title'])) {
      $title = $metatag_data['title'];
    }
    else {
      $title = $term->getName();
    }
    $metatags['title'] = $title;
    $metatags['og:title'] = $title;

    // Description.
    if (!empty($metatag_data['description'])) {
      $description = $metatag_data['description'];
    }
    else {
      $description = $this->generateDescriptionFromTerm($term);
    }
    if (!empty($description)) {
      $metatags['description'] = $description;
      $metatags['og:description'] = $description;
    }

    // Image.
    $image_url = $this->getTermImageUrl($term, $metatag_data);
    if ($image_url) {
      $metatags['og:image'] = $image_url;
    }

    // URL.
    $metatags['og:url'] = $term->toUrl('canonical', ['absolute' => TRUE])->toString();

    return $metatags;
  }

  /**
   * Generate description from node body.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   *
   * @return string
   *   The generated description.
   */
  protected function generateDescriptionFromBody(NodeInterface $node) {
    if ($node->hasField('body') && !$node->get('body')->isEmpty()) {
      $body = $node->get('body')->value;
      // Strip HTML tags and limit to 160 characters.
      $plain_text = strip_tags($body);
      $plain_text = preg_replace('/\s+/', ' ', $plain_text);
      $description = mb_substr(trim($plain_text), 0, 160);
      if (mb_strlen($plain_text) > 160) {
        $description .= '...';
      }
      return $description;
    }
    return '';
  }

  /**
   * Generate description from taxonomy term.
   *
   * @param \Drupal\taxonomy\TermInterface $term
   *   The taxonomy term entity.
   *
   * @return string
   *   The generated description.
   */
  protected function generateDescriptionFromTerm(TermInterface $term) {
    if ($term->hasField('description') && !$term->get('description')->isEmpty()) {
      $description_field = $term->get('description')->value;
      // Strip HTML tags and limit to 160 characters.
      $plain_text = strip_tags($description_field);
      $plain_text = preg_replace('/\s+/', ' ', $plain_text);
      $description = mb_substr(trim($plain_text), 0, 160);
      if (mb_strlen($plain_text) > 160) {
        $description .= '...';
      }
      return $description;
    }
    return '';
  }

  /**
   * Get image URL from node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   * @param array $metatag_data
   *   Metatag data from database.
   *
   * @return string|null
   *   The image URL or NULL.
   */
  protected function getNodeImageUrl(NodeInterface $node, $metatag_data = []) {
    // First check for metatag_image from database.
    if (!empty($metatag_data['image'])) {
      $file = File::load($metatag_data['image']);
      if ($file) {
        return $this->getAbsoluteFileUrl($file);
      }
    }

    // Fall back to field_image.
    if ($node->hasField('field_image') && !$node->get('field_image')->isEmpty()) {
      $image = $node->get('field_image')->first();
      if ($image && $image->entity) {
        return $this->getAbsoluteFileUrl($image->entity);
      }
    }
    return NULL;
  }

  /**
   * Get image URL from taxonomy term.
   *
   * @param \Drupal\taxonomy\TermInterface $term
   *   The taxonomy term entity.
   * @param array $metatag_data
   *   Metatag data from database.
   *
   * @return string|null
   *   The image URL or NULL.
   */
  protected function getTermImageUrl(TermInterface $term, $metatag_data = []) {
    // First check for metatag_image from database.
    if (!empty($metatag_data['image'])) {
      $file = File::load($metatag_data['image']);
      if ($file) {
        return $this->getAbsoluteFileUrl($file);
      }
    }

    // Then try to get image from term itself.
    if ($term->hasField('field_image') && !$term->get('field_image')->isEmpty()) {
      $image = $term->get('field_image')->first();
      if ($image && $image->entity) {
        return $this->getAbsoluteFileUrl($image->entity);
      }
    }

    // If no image, get from latest child node.
    $tid = $term->id();
    $query = $this->entityTypeManager->getStorage('node')->getQuery();
    $query->condition('status', 1)
      ->accessCheck(TRUE)
      ->sort('created', 'DESC')
      ->range(0, 1);

    // Check if there's a taxonomy reference field.
    $node_fields = $this->getNodeFieldsReferencingTaxonomy();
    if (!empty($node_fields)) {
      $group = $query->orConditionGroup();
      foreach ($node_fields as $field_name) {
        $group->condition($field_name, $tid);
      }
      $query->condition($group);

      $nids = $query->execute();
      if (!empty($nids)) {
        $nid = reset($nids);
        $node = $this->entityTypeManager->getStorage('node')->load($nid);
        if ($node) {
          return $this->getNodeImageUrl($node);
        }
      }
    }

    return NULL;
  }

  /**
   * Get node fields that reference taxonomy terms.
   *
   * @return array
   *   An array of field names.
   */
  protected function getNodeFieldsReferencingTaxonomy() {
    $fields = [];
    $field_map = \Drupal::service('entity_field.manager')->getFieldMapByFieldType('entity_reference');

    if (isset($field_map['node'])) {
      foreach ($field_map['node'] as $field_name => $field_info) {
        // Check if this field references taxonomy terms.
        foreach ($field_info['bundles'] as $bundle) {
          $field_config = \Drupal::entityTypeManager()
            ->getStorage('field_config')
            ->load("node.{$bundle}.{$field_name}");

          if ($field_config && $field_config->getSetting('target_type') === 'taxonomy_term') {
            $fields[] = $field_name;
            break;
          }
        }
      }
    }

    return array_unique($fields);
  }

  /**
   * Get absolute URL for a file entity.
   *
   * @param \Drupal\file\Entity\File $file
   *   The file entity.
   *
   * @return string
   *   The absolute URL.
   */
  protected function getAbsoluteFileUrl($file) {
    if ($file instanceof File) {
      $uri = $file->getFileUri();
      return \Drupal::service('file_url_generator')->generateAbsoluteString($uri);
    }
    return '';
  }

}
