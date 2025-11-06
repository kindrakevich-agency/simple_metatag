<?php

namespace Drupal\simple_metatag\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for managing path-based metatag overrides.
 */
class PathMetatagsForm extends FormBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a PathMetatagsForm object.
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
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simple_metatag_path_metatags_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {
    $override = NULL;

    if ($id) {
      $override = $this->database->select('simple_metatag_path', 'sm')
        ->fields('sm')
        ->condition('id', $id)
        ->execute()
        ->fetchObject();
    }

    $form['id'] = [
      '#type' => 'hidden',
      '#value' => $id,
    ];

    $form['path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Path'),
      '#description' => $this->t('Enter the path pattern (e.g., /blog/*, /about, or &lt;front&gt; for homepage). Use * as wildcard.'),
      '#required' => TRUE,
      '#default_value' => $override ? $override->path : '',
    ];

    // Get all available domains from existing overrides.
    $domain_options = $this->getAvailableDomains();
    $selected_domains = [];
    if ($override && $override->domains) {
      $selected_domains = unserialize($override->domains);
    }

    $form['domains'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Domains'),
      '#description' => $this->t('Select domains. Leave empty to apply to all domains.'),
      '#options' => $domain_options,
      '#default_value' => $selected_domains,
    ];

    $form['language'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Language'),
      '#description' => $this->t('Language code (e.g., en, es, fr). Leave empty for all languages.'),
      '#default_value' => $override ? $override->language : '',
      '#maxlength' => 12,
    ];

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Meta Title'),
      '#default_value' => $override ? $override->title : '',
      '#maxlength' => 255,
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Meta Description'),
      '#default_value' => $override ? $override->description : '',
      '#rows' => 3,
    ];

    $form['image'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Image'),
      '#description' => $this->t('Upload an image for og:image metatag.'),
      '#upload_location' => 'public://metatag/',
      '#upload_validators' => [
        'FileExtension' => [
          'extensions' => 'jpg jpeg png gif webp',
        ],
        'FileSizeLimit' => [
          'fileLimit' => '5M',
        ],
      ],
      '#default_value' => $override && $override->image ? [$override->image] : NULL,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Process domains.
    $domains = array_filter($form_state->getValue('domains'));

    // Handle file upload.
    $image_fid = NULL;
    $image_array = $form_state->getValue('image');
    if (!empty($image_array[0])) {
      $file = \Drupal\file\Entity\File::load($image_array[0]);
      if ($file) {
        $file->setPermanent();
        $file->save();
        $image_fid = $file->id();
      }
    }

    $fields = [
      'path' => $form_state->getValue('path'),
      'domains' => !empty($domains) ? serialize(array_values($domains)) : NULL,
      'language' => $form_state->getValue('language'),
      'title' => $form_state->getValue('title'),
      'description' => $form_state->getValue('description'),
      'image' => $image_fid,
      'weight' => 0,
      'status' => 1,
    ];

    $id = $form_state->getValue('id');

    if ($id) {
      // Update existing.
      $this->database->update('simple_metatag_path')
        ->fields($fields)
        ->condition('id', $id)
        ->execute();

      $this->messenger()->addStatus($this->t('Path-based metatag override updated.'));
    }
    else {
      // Insert new.
      $this->database->insert('simple_metatag_path')
        ->fields($fields)
        ->execute();

      $this->messenger()->addStatus($this->t('Path-based metatag override created.'));
    }

    $form_state->setRedirect('simple_metatag.path_metatags_list');
  }

  /**
   * Get available domains from existing overrides.
   *
   * @return array
   *   Array of domain options.
   */
  protected function getAvailableDomains() {
    $domains = [];

    // Get current request domain.
    $current_domain = \Drupal::request()->getHost();
    $domains[$current_domain] = $current_domain;

    // Get domains from existing overrides.
    $results = $this->database->select('simple_metatag_path', 'sm')
      ->fields('sm', ['domains'])
      ->execute();

    foreach ($results as $row) {
      if (!empty($row->domains)) {
        $override_domains = unserialize($row->domains);
        foreach ($override_domains as $domain) {
          $domains[$domain] = $domain;
        }
      }
    }

    return $domains;
  }

}
