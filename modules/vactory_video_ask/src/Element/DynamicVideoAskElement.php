<?php

namespace Drupal\vactory_video_ask\Element;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;

/**
 * Provide an URL form element with link attributes.
 *
 * @FormElement("dynamic_video_ask")
 */
class DynamicVideoAskElement extends FormElement {

  /**
   * Returns the element properties for this element.
   *
   * @return array
   *   An array of element properties. See
   *   \Drupal\Core\Render\ElementInfoManagerInterface::getInfo() for
   *   documentation of the standard properties of all elements, and the
   *   return value format.
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#input' => TRUE,
      '#process' => [
        [$class, 'processDynamicVideoAsk'],
      ],
      '#element_validate' => [
        [$class, 'validateDynamicVideoAsk'],
      ],
      '#theme_wrappers' => ['fieldset'],
      '#attached' => [
        'library' => ['vactory_video_ask/video-ask-form'],
      ],
    ];
  }

  /**
   * Video Ask form element process callback.
   */
  public static function processDynamicVideoAsk(&$element, FormStateInterface $form_state, &$form) {
    $default_value = isset($element['#default_value']) ? $element['#default_value'] : '';
    $parents = $element['#parents'];
    $id_prefix = implode('-', $parents);
    $wrapper_id = Html::getUniqueId($id_prefix . '-add-more-wrapper');
    // states.
    $element_state = static::getElementState($parents, $form_state);
    if ($element_state === NULL) {
      $element_state = [
        'video_ask' => [],
        'items_count' => !empty($default_value) ? count($default_value) - 1 : 0,
      ];
      static::setElementState($parents, $form_state, $element_state);
    }

    $max = $element_state['items_count'];

    $element['screen'] = [
      '#type' => 'vertical_tabs',
      '#default_tab' => 'screen_details',
    ];

    // Background options.
    $background_options = [
      '-1' => t('-- selectionner une layout --'),
      'image' => t('Image'),
      'video' => t('Vidéo'),
      'wysiwyg' => t('Champ Wysiwyg'),
    ];

    // Type response options.
    $type_response_options = [
      '-1' => t('aucun réponse'),
      'button' => t('Button'),
      'quiz' => t('Quiz'),
      'multiple_choices' => t('Multiple Choices'),
    ];

    $user_input_values = $form_state->getUserInput();

    for ($i = 0, $j = 0; $i <= $max; $i++) {
      $screen_to_delete = isset($element_state['video_ask'][$i]['screen_to_delete']) ? $element_state['video_ask'][$i]['screen_to_delete'] : NULL;
      if (isset($screen_to_delete) && (int) $screen_to_delete == $i) {
        continue;
      }
      $element['screen_details'][$i] = [
        '#type' => 'details',
        '#title' => t("Screen %j", ["%j" => $j + 1]),
        '#group' => 'screen',
        '#tree' => TRUE,
      ];
      $element['screen_details'][$i]['remove_screen'] = [
        '#type' => 'button',
        '#value' => t('delete screen'),
        '#name' => 'delete_screen_btn_' . $i,
        '#prefix' => '<div class="remove-setting-submit">',
        '#suffix' => '</div>',
        '#limit_validation_errors' => [],
        '#ajax' => [
          'callback' => [static::class, 'deleteFormCallback'],
          'method' => 'replace',
        ],
      ];

      $element['screen_details'][$i]['id'] = [
        '#type' => 'textfield',
        '#title' => t('Id screen'),
        '#required' => TRUE,
        '#default_value' => (isset($user_input_values[$i]['id']) && !empty($user_input_values[$i]['id'])) ?
        $user_input_values[$i]['id'] : ((isset($default_value[$i]['id']) && !empty($default_value[$i]['id'])) ? $default_value[$i]['id'] : ''),
      ];

      $background_wrapper = 'background_layout_selector_' . $i;

      $element['screen_details'][$i]['Layout'] = [
        '#type' => 'details',
        '#title' => t('Layout'),
        '#collapsible' => TRUE,
        '#collapsed' => TRUE,
        '#open' => TRUE,
        '#prefix' => "<div id='$background_wrapper'>",
        '#suffix' => "</div>",
      ];
      $element['screen_details'][$i]['Layout']['background'] = [
        '#type' => 'select',
        '#title' => t('Layout'),
        '#options' => $background_options,
        '#ajax' => [
          'callback' => [static::class, 'onChangeLayout'],
          'wrapper' => $background_wrapper,
        ],
        '#default_value' => (isset($user_input_values[$i]['Layout']['background']) && !empty($user_input_values[$i]['Layout']['background'])) ?
        $user_input_values[$i]['Layout']['background'] : ((isset($default_value[$i]['Layout']['background']) && !empty($default_value[$i]['Layout']['background']))
            ? $default_value[$i]['Layout']['background'] : '-1'),
      ];

      $element['update_background_' . $i] = [
        '#type'                    => 'submit',
        '#value'                   => t('Update widget'),
        '#name' => 'update_background_' . $i,
        '#attributes' => [
          'style' => ['display:none;'],
        ],
        '#ajax'                    => [
          'callback' => [static::class, 'updateWidgetLayoutBackground'],
          'wrapper'  => $background_wrapper,
          'event'    => 'click',
        ],
        '#limit_validation_errors' => [$element['#array_parents']],
        '#submit' => [[static::class, 'updateItemsLayoutBackground']],
      ];

      $bg_selected = isset($element_state['video_ask'][$i]['selected_layout']) ? $element_state['video_ask'][$i]['selected_layout'] :
        (isset($default_value[$i]['response']) && !empty($default_value[$i]['Layout']) ? $default_value[$i]['Layout']['background'] : []);
      if (!empty($bg_selected)) {
        switch ($bg_selected) {
          case 'image':
            $element['screen_details'][$i]['Layout']['image'] = [
              '#type' => 'media_library',
              '#title' => t('Image'),
              '#allowed_bundles' => ['image'],
              '#required' => TRUE,
              '#upload_validators' => [
                'file_validate_extensions' => ['png svg'],
                'file_validate_size' => [25600000],
              ],
              '#upload_location' => 'public://locator/vactory_video_ask',
              '#default_value' => (isset($user_input_values[$i]['Layout']['image']) && !empty($user_input_values[$i]['Layout']['image'])) ?
              $user_input_values[$i]['Layout']['image']['media_library_selection'] : ((isset($default_value[$i]['Layout']['image']) && !empty($default_value[$i]['Layout']['image']))
              ? $default_value[$i]['Layout']['image']['id'] : NULL),
            ];
            break;

          case 'video':
            $element['screen_details'][$i]['Layout']['url'] = [
              '#type' => 'textfield',
              '#title' => t('Vidéo url'),
              '#required' => TRUE,
              '#default_value' => (isset($user_input_values[$i]['Layout']['url']) && !empty($user_input_values[$i]['Layout']['url'])) ?
              $user_input_values[$i]['Layout']['url'] : ((isset($default_value[$i]['Layout']['url']) && !empty($default_value[$i]['Layout']['url']))
                ? $default_value[$i]['Layout']['url'] : ''),
            ];
            break;

        }
      }
      $element['screen_details'][$i]['Layout']['text'] = [
        '#type' => 'text_format',
        '#title' => t('Text'),
        '#format' => 'full_html',
        '#required' => TRUE,
        '#default_value' => (isset($user_input_values[$i]['Layout']['text']) && !empty($user_input_values[$i]['Layout']['text'])) ?
        $user_input_values[$i]['Layout']['text']['value'] : ((isset($default_value[$i]['Layout']['text']) && !empty($default_value[$i]['Layout']['text']))
          ? $default_value[$i]['Layout']['text']['value'] : ''),
      ];

      $response_wrapper = 'response_layout_selector_' . $i;
      $element['screen_details'][$i]['response'] = [
        '#type' => 'details',
        '#title' => t('Résponse'),
        '#collapsible' => TRUE,
        '#collapsed' => TRUE,
        '#open' => TRUE,
        '#prefix' => "<div id='$response_wrapper'>",
        '#suffix' => "</div>",
      ];

      // Type de réponse.
      $element['screen_details'][$i]['response']['type_response'] = [
        '#type' => 'select',
        '#title' => t('Type réponses'),
        '#options' => $type_response_options,
        '#ajax' => [
          'callback' => [static::class, 'onChangeResponseType'],
          'wrapper' => $response_wrapper,
        ],
        '#default_value' => (isset($user_input_values[$i]['response']) && !empty($user_input_values[$i]['response'])) ?
        $user_input_values[$i]['response'] : ((isset($default_value[$i]['response']) && !empty($default_value[$i]['response']))
          ? $default_value[$i]['response'] : ''),
      ];

      $element['update_type_response_' . $i] = [
        '#type'                    => 'submit',
        '#value'                   => t('Update type response'),
        '#name' => 'update_type_response_' . $i,
        '#attributes' => [
          'style' => ['display:none;'],
        ],
        '#ajax'                    => [
          'callback' => [static::class, 'updateWidgetTypeResponse'],
          'wrapper'  => $response_wrapper,
          'event'    => 'click',
        ],
        '#limit_validation_errors' => [$element['#array_parents']],
        '#submit' => [[static::class, 'updateItemsTypeResponse']],
      ];
      $type_response_selected = isset($element_state['video_ask'][$i]['response_type']) ? $element_state['video_ask'][$i]['response_type'] : (isset($default_value[$i]['response']) && !empty($default_value[$i]['response']) ? $default_value[$i]['response']['type_response'] : []);
      if (!empty($type_response_selected)) {
        switch ($type_response_selected) {
          case 'button':
            $element['screen_details'][$i]['response']['settings'] = [
              '#type' => 'video_ask_button',
              '#title' => t('Button Response'),
              '#button_id' => $i,
              '#default_value' => isset($default_value[$i]['response']['settings']['label']) && !empty($default_value[$i]['response']['settings']['label']) ? $default_value[$i]['response']['settings']['label'] : [],
            ];
            break;

          case 'quiz':
            $element['screen_details'][$i]['response']['settings'] = [
              '#type' => 'video_ask_quiz',
              '#title' => t('Quiz'),
              '#quiz_id' => $i,
              '#cardinality' => -1,
              '#default_value' => (isset($default_value[$i]['response']['settings']) && !empty($default_value[$i]['response']['settings'])) ? $default_value[$i]['response']['settings'] : [],
            ];
            break;

          case 'multiple_choices':
            $element['screen_details'][$i]['response']['settings'] = [
              '#type' => 'video_ask_multiple_choice',
              '#title' => t('Multiple choices'),
              '#multiple_choice_id' => $i,
              '#default_value' => (isset($default_value[$i]['response']['settings']) && !empty($default_value[$i]['response']['settings']))
              ? $default_value[$i]['response']['settings'] : [],
            ];
            break;

        }
      }

      $element['delete_screen_' . $i] = [
        '#type'                    => 'submit',
        '#value'                   => t('Delete screen'),
        '#name' => 'delete_screen_' . $i,
        '#attributes' => [
          'style' => ['display:none;'],
        ],
        '#ajax'                    => [
          'callback' => [static::class, 'updateScreensWidget'],
          'wrapper' => $wrapper_id,
          'event'    => 'click',
        ],
        '#limit_validation_errors' => [$element['#array_parents']],
        '#submit' => [[static::class, 'updateScreensAfterDelete']],
      ];
      $j++;
    }

    $element['#prefix'] = '<div id="' . $wrapper_id . '">';
    $element['#suffix'] = '</div>';
    $element['add_more'] = [
      '#type' => 'submit',
      '#name' => strtr($id_prefix, '-', '_') . '_add_more',
      '#value' => "add more",
      '#attributes' => ['class' => ['id-label-add-more-submit']],
      '#submit' => [[static::class, 'addMoreSubmit']],
      '#ajax' => [
        'callback' => [static::class, 'addMoreAjax'],
        'wrapper' => $wrapper_id,
        'effect' => 'fade',
      ],
    ];

    return $element;
  }

  /**
   * Get the element state function.
   */
  public static function getElementState(array $parents, FormStateInterface $form_state): ?array {
    return NestedArray::getValue($form_state->getStorage(), $parents);
  }

  /**
   * Set the element state function.
   */
  public static function setElementState(array $parents, FormStateInterface $form_state, array $field_state): void {
    NestedArray::setValue($form_state->getStorage(), $parents, $field_state);
  }

  /**
   * Add More ajax btn.
   */
  public static function addMoreAjax(array $form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();
    $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -1));
    return $element;
  }

  /**
   * Add more submit.
   */
  public static function addMoreSubmit(array $form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();
    $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -1));
    $parents = $element['#parents'];
    $element_state = static::getElementState($parents, $form_state);
    $element_state['items_count']++;
    static::setElementState($parents, $form_state, $element_state);
    $form_state->setRebuild();
  }

  /**
   * On change layout function.
   */
  public static function onChangeLayout(array $form, FormStateInterface $form_state) {
    $select = $form_state->getTriggeringElement();
    preg_match_all('!\d+!', $select['#name'], $matches);
    $i = $matches[0][0];
    $response = new AjaxResponse();
    $response->addCommand(new InvokeCommand("[name=update_background_$i]", 'trigger', ['click']));
    return $response;
  }

  /**
   * On change response type.
   */
  public static function onChangeResponseType(array $form, FormStateInterface $form_state) {
    $select = $form_state->getTriggeringElement();
    preg_match_all('!\d+!', $select['#name'], $matches);
    $i = $matches[0][0];
    $response = new AjaxResponse();
    $response->addCommand(new InvokeCommand("[name=update_type_response_$i]", 'trigger', ['click']));
    return $response;
  }

  /**
   * The delete screen callback.
   */
  public static function deleteFormCallback(array $form, FormStateInterface $form_state) {
    $select = $form_state->getTriggeringElement();
    preg_match_all('!\d+!', $select['#name'], $matches);
    $i = $matches[0][0];
    $response = new AjaxResponse();
    $response->addCommand(new InvokeCommand("[name=delete_screen_$i]", 'trigger', ['click']));
    return $response;
  }

  /**
   * Dynamic video ask form element validate callback.
   */
  public static function validateDynamicVideoAsk(&$element, FormStateInterface $form_state, &$form) {
    $values = $form_state->getValues();
    foreach ($values as $key => $value) {
      if (!is_numeric($key)) {
        unset($values[$key]);
      }
      else {
        if (array_key_exists('remove_screen', $values[$key])) {
          unset($values[$key]['remove_screen']);
        }
        if (array_key_exists('update_background', $values[$key]['Layout'])) {
          unset($values[$key]['Layout']['update_background']);
        }
        if (array_key_exists('update_type_response', $values[$key]['response'])) {
          unset($values[$key]['response']['update_type_response']);
        }
        if (array_key_exists('settings', $values[$key]['response'])) {
          if (array_key_exists('add_more', $values[$key]['response']['settings'])) {
            unset($values[$key]['response']['settings']['add_more']);
          }
          if (array_key_exists('delete_item', $values[$key]['response']['settings'])) {
            unset($values[$key]['response']['settings']['delete_item']);
          }
          if (isset($values[$key]['response']['settings']) && !empty($values[$key]['response']['settings'])) {
            if (!array_key_exists('answers', $values[$key]['response']['settings'])) {
              foreach ($values[$key]['response']['settings'] as $index => $item) {
                if (is_numeric($index)) {
                  unset($values[$key]['response']['settings'][$index]['answers']['add_more']);
                  unset($values[$key]['response']['settings'][$index]['answers']['delete_item']);
                }
              }
            }
            else {
              unset($values[$key]['response']['settings']['answers']['add_more']);
              unset($values[$key]['response']['settings']['answers']['delete_item']);
            }
          }
        }
      }

      // Load image.
      if (isset($values[$key]['Layout']) && !empty($values[$key]['Layout'])) {
        $bg = $values[$key]['Layout']['background'];
        if ($bg == 'image') {
          $mid = $values[$key]['Layout']['image'];
          if (isset($mid) && !empty($mid)) {
            $media = Media::load($mid);
            if (isset($media) && !empty($media)) {
              $fid = $media->field_media_image->target_id;
              $file = File::load($fid);
              $file->setPermanent();
              $file->save();
              $url = file_url_transform_relative(\Drupal::service('stream_wrapper_manager')->getViaUri($file->getFileUri())->getExternalUrl());
              $image = [
                'id' => $mid,
                'url' => $url,
              ];
              $values[$key]['Layout']['image'] = $image;
            }
          }
        }
      }
    }
    $values = array_values($values);
    $form_state->setValues($values);
  }

  /**
   * Update items layout background callback.
   */
  public static function updateItemsLayoutBackground(array $form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();
    $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -1));
    $parents = $element['#parents'];
    $element_state = static::getElementState($parents, $form_state);
    preg_match_all('!\d+!', $button['#name'], $matches);
    $i = $matches[0][0];
    $element_state['video_ask'][$i]['selected_layout'] = $element['screen_details'][$i]['Layout']['background']['#value'];
    static::setElementState($parents, $form_state, $element_state);
    $form_state->setRebuild();
  }

  /**
   * Update widget layout background callback.
   */
  public static function updateWidgetLayoutBackground(array $form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();
    $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -1));
    preg_match_all('!\d+!', $button['#name'], $matches);
    $i = $matches[0][0];
    return $element['screen_details'][$i]['Layout'];
  }

  /**
   * Update items type response.
   */
  public static function updateItemsTypeResponse(array $form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();
    $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -1));
    $parents = $element['#parents'];
    $element_state = static::getElementState($parents, $form_state);
    preg_match_all('!\d+!', $button['#name'], $matches);
    $i = $matches[0][0];
    $element_state['video_ask'][$i]['response_type'] = $element['screen_details'][$i]['response']['type_response']['#value'];
    static::setElementState($parents, $form_state, $element_state);
    $form_state->setRebuild();
  }

  /**
   * Update widget type response.
   */
  public static function updateWidgetTypeResponse(array $form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();
    preg_match_all('!\d+!', $button['#name'], $matches);
    $i = $matches[0][0];
    $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -1));
    return $element['screen_details'][$i]['response'];
  }

  /**
   * Update screens after delete.
   */
  public static function updateScreensAfterDelete(array $form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();
    $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -1));
    $parents = $element['#parents'];
    $element_state = static::getElementState($parents, $form_state);
    preg_match_all('!\d+!', $button['#name'], $matches);
    $i = $matches[0][0];
    $element_state['video_ask'][$i]['screen_to_delete'] = $i;
    static::setElementState($parents, $form_state, $element_state);
    $form_state->setRebuild();
  }

  /**
   * Update screens widget.
   */
  public static function updateScreensWidget(array $form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();
    $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -1));
    return $element;
  }

}
