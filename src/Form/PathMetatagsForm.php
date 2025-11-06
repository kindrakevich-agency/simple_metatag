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

    $form['domains'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Domains'),
      '#description' => $this->t('Enter one domain per line. Leave empty to apply to all domains.'),
      '#default_value' => $override && $override->domains ? implode("\n", unserialize($override->domains)) : '',
      '#rows' => 3,
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
      '#required' => TRUE,
      '#default_value' => $override ? $override->title : '',
      '#maxlength' => 255,
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Meta Description'),
      '#required' => TRUE,
      '#default_value' => $override ? $override->description : '',
      '#rows' => 3,
    ];

    $form['image'] = [
      '#type' => 'url',
      '#title' => $this->t('Image URL'),
      '#description' => $this->t('Full URL to the og:image (e.g., https://example.com/image.jpg).'),
      '#default_value' => $override ? $override->image : '',
      '#maxlength' => 512,
    ];

    $form['weight'] = [
      '#type' => 'number',
      '#title' => $this->t('Weight'),
      '#description' => $this->t('Higher weight = higher priority. More specific paths should have higher weight.'),
      '#default_value' => $override ? $override->weight : 0,
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Active'),
      '#default_value' => $override ? $override->status : 1,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => \Drupal\Core\Url::fromRoute('simple_metatag.path_metatags_list'),
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $domains_text = $form_state->getValue('domains');
    $domains = array_filter(array_map('trim', explode("\n", $domains_text)));

    $fields = [
      'path' => $form_state->getValue('path'),
      'domains' => !empty($domains) ? serialize($domains) : NULL,
      'language' => $form_state->getValue('language'),
      'title' => $form_state->getValue('title'),
      'description' => $form_state->getValue('description'),
      'image' => $form_state->getValue('image'),
      'weight' => $form_state->getValue('weight'),
      'status' => $form_state->getValue('status') ? 1 : 0,
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

}
