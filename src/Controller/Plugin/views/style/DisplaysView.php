<?php

/**
 * @file
 * Definition of Drupal\tentamenbank\Plugin\views\style\DisplaysView.
 */

namespace Drupal\tentamenbank\Plugin\views\style;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\style\StylePluginBase;

/**
 * Style plugin to render a list of years and months
 * in reverse chronological order linked to content.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "displays",
 *   title = @Translation("Displays"),
 *   help = @Translation("Render a list of years and months in reverse chronological order linked to content."),
 *   theme = "views_view_displays",
 *   display_types = { "normal" }
 * )
 */
class DisplaysView extends StylePluginBase {

  /**
   * Set default options.
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['path'] = ['default' => 'displays'];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    // Path prefix for TARDIS links.
    $form['path'] = [
      '#type' => 'textfield',
      '#title' => t('Link path'),
      '#default_value' => (isset($this->options['path'])) ? $this->options['path'] : 'displays',
      '#description' => t('Path prefix for each TARDIS link, eg. example.com<strong>/tardis/</strong>1963/11.'),
    ];

    // Month date format.
    $form['month_date_format'] = [
      '#type' => 'textfield',
      '#title' => t('Month date format'),
      '#default_value' => (isset($this->options['month_date_format'])) ? $this->options['month_date_format'] : 'm',
      '#description' => t('Valid PHP <a href="@url" target="_blank">Date format</a> parameter to display months.', [
        '@url' => 'https://www.php.net/manual/en/datetime.format.php'
      ]),
    ];

    // Whether month links should be nested inside year links.
    $options = [
      1 => 'yes',
      0 => 'no',
    ];
    $form['nesting'] = [
      '#type' => 'radios',
      '#title' => t('Nesting'),
      '#options' => $options,
      '#default_value' => (isset($this->options['nesting'])) ? $this->options['nesting'] : 1,
      '#description' => t('Should months be nested inside years? <br />
        Example:
        <table style="width:100px">
          <thead>
              <th>Nesting</th>
              <th>No nesting</th>
          </thead>
          <tbody>
            <td>
              <ul>
                <li>1963
                  <ul>
                    <li>12</li>
                    <li>11</li>
                    <li>10</li>
                  </ul>
                </li>
              </ul>
            </td>
            <td>
              <ul>
                <li>1963/12</li>
                <li>1963/11</li>
                <li>1963/10</li>
              </ul>
            </td>
          </tbody>
        </table>
      '),
    ];

    // Extra CSS classes.
    $form['classes'] = [
      '#type' => 'textfield',
      '#title' => t('CSS classes'),
      '#default_value' => (isset($this->options['classes'])) ? $this->options['classes'] : 'view-displays',
      '#description' => t('CSS classes for further customization of this TARDIS page.'),
    ];
  }
}
?>