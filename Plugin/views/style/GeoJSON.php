<?php

// TODO: Is this how this should be namespaced?
namespace views_geojson\Plugin\views\style;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\PluginBase;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\wizard\WizardInterface;
use Drupal\views\Plugin\views\style\StylePluginBase;
use Drupal\views\ViewExecutable;

class GeoJSON extends StylePluginBase {

  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
  }

  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['data_source'] = array(
      'contains' => array(
        'value' => array('default' => 'asc'),
        'latitude' => array('default' => 0),
        'longitude' => array('default' => 0),
        'geofield' => array('default' => 0),
        'wkt' => array('default' => 0),
        'name_field' => array('default' => 0),
        'description_field' => array('default' => 0),
      ),
    );
    $options['attributes'] = array('default' => NULL, 'translatable' => FALSE);
    $options['jsonp_prefix'] = array(
      'default' => NULL,
      'translatable' => FALSE
    );
    $options['content_type'] = array(
      'default' => 'default',
      'translatable' => FALSE
    );
    $options['using_views_api_mode'] = array(
      'default' => FALSE,
      'translatable' => FALSE
    );
    return $options;
  }

  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $fields = array();

    // Get list of fields in this view & flag available geodata fields.
    $handlers = $this->displayHandler->getHandlers('field');

    // Check for any fields, as the view needs them.
    if (empty($handlers)) {
      $form['error_markup'] = array(
        '#value' => t('You need to enable at least one field before you can configure your field settings'),
        '#prefix' => '<div class="error form-item description">',
        '#suffix' => '</div>',
      );
      return;
    }

    // Go through fields.
    foreach ($handlers as $field_id => $handler) {
      $fields[$field_id] = $handler->definition['title'];

      $field_info = NULL;
      if (!empty($handler->field_info)) {
        $field_info = $handler->field_info;
      }
      // Check if $handler is an entity views handler
      elseif ($this->is_entity_views_handler($handler)) {
        // Strip the basic field name from the entity views handler field and
        // fetch the field info for it.
        $property = EntityFieldHandlerHelper::get_selector_field_name($handler->real_field);
        if ($field_name = EntityFieldHandlerHelper::get_selector_field_name(substr($handler->real_field, 0, strpos($handler->real_field, ':' . $property)), ':')) {
          $field_info = field_info_field($field_name);
        }
      }

      $fields_info[$field_id]['type'] = $field_info['type'];
    }

    // Default data source.
    $data_source_options = array(
      'latlon' => t('Other: Lat/Lon Point'),
      'geofield' => t('Geofield'),
      'wkt' => t('WKT'),
    );

    // Data Source options.
    $form['data_source'] = array(
      '#type' => 'fieldset',
      '#tree' => TRUE,
      '#title' => t('Data Source'),
    );

    $form['data_source']['value'] = array(
      '#type' => 'select',
      '#multiple' => FALSE,
      '#title' => t('Map Data Sources'),
      '#description' => t('Choose which sources of data that the map will provide features for.'),
      '#options' => $data_source_options,
      '#default_value' => $this->options['data_source']['value'],
    );

    // Other Lat and Lon data sources.
    if (count($fields) > 0) {
      $form['data_source']['latitude'] = array(
        '#type' => 'select',
        '#title' => t('Latitude Field'),
        '#description' => t('Choose a field for Latitude.  This should be a field that is a decimal or float value.'),
        '#options' => $fields,
        '#default_value' => $this->options['data_source']['latitude'],
        '#process' => array('ctools_dependent_process'),
        '#dependency' => array('edit-style-options-data-source-value' => array('latlon')),
      );

      $form['data_source']['longitude'] = array(
        '#type' => 'select',
        '#title' => t('Longitude Field'),
        '#description' => t('Choose a field for Longitude.  This should be a field that is a decimal or float value.'),
        '#options' => $fields,
        '#default_value' => $this->options['data_source']['longitude'],
        '#process' => array('ctools_dependent_process'),
        '#dependency' => array('edit-style-options-data-source-value' => array('latlon')),
      );

      // Get Geofield-type fields.
      $geofield_fields = array();
      foreach ($fields as $field_id => $field) {
        if ($fields_info[$field_id]['type'] == 'geofield') {
          $geofield_fields[$field_id] = $field;
        }
      }

      // Geofield.
      $form['data_source']['geofield'] = array(
        '#type' => 'select',
        '#title' => t('Geofield'),
        '#description' => t("Choose a Geofield field. Any formatter will do; we'll access Geofield's underlying WKT format."),
        '#options' => $geofield_fields,
        '#default_value' => $this->options['data_source']['geofield'],
        '#process' => array('ctools_dependent_process'),
        '#dependency' => array('edit-style-options-data-source-value' => array('geofield')),
      );

      // WKT.
      $form['data_source']['wkt'] = array(
        '#type' => 'select',
        '#title' => t('WKT'),
        '#description' => t('Choose a WKT format field.'),
        '#options' => $fields,
        '#default_value' => $this->options['data_source']['wkt'],
        '#process' => array('ctools_dependent_process'),
        '#dependency' => array('edit-style-options-data-source-value' => array('wkt')),
      );
    }

    $form['data_source']['name_field'] = array(
      '#type' => 'select',
      '#title' => t('Title Field'),
      '#description' => t('Choose the field to appear as title on tooltips.'),
      '#options' => array_merge(array('' => ''), $fields),
      '#default_value' => $this->options['data_source']['name_field'],
    );

    $form['data_source']['description_field'] = array(
      '#type' => 'select',
      '#title' => t('Description'),
      '#description' => t('Choose the field or rendering method to appear as
          description on tooltips.'),
      '#required' => FALSE,
      '#options' => array_merge(array('' => ''), $fields),
      '#default_value' => $this->options['data_source']['description_field'],
    );

    // Attributes and variable styling description.
    $form['attributes'] = array(
      '#type' => 'fieldset',
      '#title' => t('Attributes and Styling'),
      '#description' => t('Attributes are field data attached to each feature.  This can be used with styling to create Variable styling.'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    );

    $form['jsonp_prefix'] = array(
      '#type' => 'textfield',
      '#title' => t('JSONP prefix'),
      '#default_value' => $this->options['jsonp_prefix'],
      '#description' => t('If used the JSON output will be enclosed with parentheses and prefixed by this label, as in the JSONP format.'),
    );

    $form['content_type'] = array(
      '#type' => 'radios',
      '#title' => t('Content-Type'),
      '#options' => array(
        'default' => t("Default: application/json"),
        'text/json' => t('text/json'),
      ),
      '#default_value' => $this->options['content_type'],
      '#description' => t('The Content-Type header that will be sent with the JSON output.'),
    );

    // Make array of attributes.
    $variable_fields = array();
    // Add name and description.
    if (!empty($this->options['data_source']['name_field'])) {
      $variable_fields['name'] = '${name}';
    }
    if (!empty($this->options['data_source']['description_field'])) {
      $variable_fields['description'] = '${description}';
    }
    // Go through fields.
    foreach ($this->view->display_handler->get_handlers('field') as $field => $handler) {
      if (($field != $this->options['data_source']['name_field']) && ($field != $this->options['data_source']['description_field'])) {
        $variable_fields[$field] = '${' . $field . '}';
      }
    }
    $variables_list = array(
      'items' => $variable_fields,
      'attributes' => array('class' => array('description'))
    );

    $markup = '<p class="description">' . t('Fields added to this view will be attached to their respective feature, (point, line, polygon,) as attributes. These attributes can then be used to add variable styling to your themes. This is accomplished by using the %syntax syntax in the values for a style.  The following is a list of formatted variables that are currently available; these can be placed right in the style interface.', array('%syntax' => '${field_name}')) . '</p>';
    $markup .= theme('item_list', $variables_list);
    $markup .= '<p class="description">' . t('Please note that this does not apply to Grouped Displays.') . '</p>';

    $form['attributes']['styling'] = array(
      '#type' => 'markup',
      '#markup' => $markup,
    );
  }

  public function render() {
  }

}