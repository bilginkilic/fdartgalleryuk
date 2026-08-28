<?php

namespace FluentFormPro\classes\Inventory;

use FluentForm\App\Models\SubmissionMeta;
use FluentForm\App\Modules\Form\FormFieldsParser;
use FluentForm\App\Services\Report\ReportHelper;
use FluentForm\Framework\Helpers\ArrayHelper as Arr;

/**
 *  Inventory Fields Validation
 *
 * @since 4.3.13
 */
class InventoryValidation
{
    protected $form;
    protected $formData;
    private $quantityItems;
    private $usedGlobalItemsRecords;
    private $globalInventoryItems;
    private $globalInventoryFieldsName;
    
    public function __construct($formData, $form)
    {
        $this->formData = $formData;
        $this->form = $form;
        $this->maybeSetItemQuantity();
    }
    
    private function maybeSetItemQuantity()
    {
        $this->quantityItems = InventoryFieldsRenderer::getQuantityFieldsMapping($this->form);
    }
    
    /**
     * Validates Inventory Items
     */
    public function validate()
    {
        $this->validateSimpleInventory();
        $this->validateGlobalInventory();
    }
    
    public function getQuantity($productName, $formData)
    {
        $quantity = 1;
        if (!$this->quantityItems) {
            return $quantity;
        }
        if (!isset($this->quantityItems[$productName])) {
            return $quantity;
        }
        $inputName = $this->quantityItems[$productName];
        $quantity = Arr::get($formData, $inputName);
        if (!$quantity) {
            return 0;
        }
        return intval($quantity);
    }
    
    public static function getPaymentItemSubmissionQuantity($formId, $parentName, $name, $price)
    {
        static $quantityCache = [];
        if (!isset($quantityCache[$formId])) {
            $quantityCache[$formId] = self::runSumQuantityQuery($formId);
        }
        if (!empty($quantityCache[$formId])) {
            foreach ($quantityCache[$formId] as $qty) {
                if ($qty->item_name == $name && $qty->item_price == $price && $qty->parent_holder == $parentName) {
                    return (int)$qty->total_count;
                }
            }
        }
        return 0;
    }
    
    public static function getItemFromOptionName($item, $key)
    {
        $isPaymentInput = Arr::get($item, 'settings.is_payment_field') == 'yes';
        if ($isPaymentInput) {
            $options = Arr::get($item, 'settings.pricing_options');
        } else {
            $options = Arr::get($item, 'settings.advanced_options');
        }
        $selectedOption = [];
        if (empty($options)) {
            return false;
        }
        foreach ($options as $option) {
            $label = sanitize_text_field($option['label']);
            $value = sanitize_text_field($option['value']);
            if ($label == $key || $value == $key) {
                $selectedOption = $option;
            }
        }
        if (!$selectedOption || empty($selectedOption['value'])) {
            return false;
        }
        
        return [
            'parent_input_name' => Arr::get($item, 'attributes.name'),
            'item_name'         => Arr::get($selectedOption, 'label'),
            'item_value'        => Arr::get($selectedOption, 'value'),
            'global_inventory'=> Arr::get($selectedOption, 'global_inventory'),
            'quantity'          => Arr::get($selectedOption, 'quantity')
        ];
    }
    
    public static function getRegularItemUsedQuantity($previousSubmissionData, $item)
    {
        $name = Arr::get($item, 'parent_input_name');
        $optionName = Arr::get($item, 'item_name');
        $optionValue = Arr::get($item, 'item_value');
        
        $data = Arr::get($previousSubmissionData, $name . '.reports');
        if (!empty($data)) {
            foreach ($data as $datum) {
                if (($datum['value'] == $optionName) || $datum['value'] == $optionValue) {
                    return intval($datum['count']);
                }
            }
        }
        return 0;
    }
    
    private function handleSinglePaymentInput($inputName, $item, $inventoryType = 'simple')
    {
        $selectedQuantity = $this->getQuantity($inputName, $this->formData);
        if (!$selectedQuantity) {
            throw new \Exception("continue");
        }
        
        $itemName = Arr::get($item, 'settings.label');
        $parentName = Arr::get($item, 'attributes.name');
        
        $availableQuantity = 0;
        $itemPrice = \FluentFormPro\Payments\PaymentHelper::convertToCents(Arr::get($item, 'attributes.value'));
        $usedQuantity = 0;
        if ($inventoryType == 'simple') {
            $availableQuantity = (int)Arr::get($item, 'settings.single_inventory_stock');
            $usedQuantity = $this->getPaymentItemSubmissionQuantity($this->form->id, $parentName, $itemName, $itemPrice) + $selectedQuantity;
        } elseif ($inventoryType == 'global') {
            $attachedGlobalInventory = Arr::get($this->globalInventoryFieldsName, $inputName);
            $availableQuantity = intval(Arr::get($this->globalInventoryItems, $attachedGlobalInventory . '.quantity'));
            $usedQuantity = (int)Arr::get($this->usedGlobalItemsRecords, $attachedGlobalInventory) + $selectedQuantity;
            // Update the internally tracked quantity for the same global inventory slug
            // to handle validations when the same form maps to multiple fields for this inventory.
            $this->usedGlobalItemsRecords[$attachedGlobalInventory] = $usedQuantity;
        }

        if ($usedQuantity > $availableQuantity) {
            throw new \Exception("stock-out");
        }
    }
    
    private function handleRadioSelect($key, $item, $prevSubmissions, $isPaymentInput, $inventoryType = 'simple')
    {
        $item = self::getItemFromOptionName($item, $this->formData[$key]);
        
        if ($item) {
            $availableQuantity = $item['quantity'];
            $usedQuantity = 0;
            $selectedQuantity = 1;
            if ($inventoryType == 'simple') {
                $itemName = $item['item_name'];
                if ($isPaymentInput) {
                    $selectedQuantity = $this->getQuantity($item['parent_input_name'], $this->formData);
                    $itemPrice = \FluentFormPro\Payments\PaymentHelper::convertToCents($item['item_value']) ;
                    $usedQuantity = $this->getPaymentItemSubmissionQuantity($this->form->id, $item['parent_input_name'], $itemName, $itemPrice);
                } else {
                    $usedQuantity = $this->getRegularItemUsedQuantity($prevSubmissions, $item);
                }
            } elseif ($inventoryType == 'global') {
                if ($isPaymentInput) {
                    $selectedQuantity = $this->getQuantity($item['parent_input_name'], $this->formData);
                }
                $availableQuantity = intval(Arr::get($this->globalInventoryItems, $item['global_inventory'] . '.quantity'));
                $usedQuantity = (int)Arr::get($this->usedGlobalItemsRecords, $item['global_inventory']);
            }
            
            $usedQuantity += $selectedQuantity;
        
            if ($usedQuantity > $availableQuantity) {
                throw new \Exception("stock-out");
            }
        }
    }

    /**
     * @throws \Exception
     */
    private function handleCheckbox($key, $field, $prevSubmissions, $isPaymentInput, $inventoryType = 'simple')
    {
        $selectedItems = $this->formData[$key];

        //Global inventory multiple option can use same slug
        //Store total selected quantity
        $totalSelectedQuantity = [];

        foreach ($selectedItems as $selectedItem) {
            $item = $this->getItemFromOptionName($field, $selectedItem);
            if (!$item) {
                throw new \Exception("continue");
            }
            $selectedQuantity = 1;
            if ($inventoryType == 'simple') {
                $availableQuantity = $item['quantity'];
                if ($isPaymentInput) {
                    $itemName = $item['item_name'];
                    $itemPrice = \FluentFormPro\Payments\PaymentHelper::convertToCents($item['item_value']);
                    $usedQuantity = $this->getPaymentItemSubmissionQuantity($this->form->id, $item['parent_input_name'], $itemName, $itemPrice);
                    $selectedQuantity = $this->getQuantity($item['parent_input_name'], $this->formData);
                    if (!$selectedQuantity) {
                        throw new \Exception("continue");
                    }
                } else {
                    $usedQuantity = $this->getRegularItemUsedQuantity($prevSubmissions, $item);
                }
                $this->validateStockOut($usedQuantity + $selectedQuantity, $availableQuantity);
            } elseif ($inventoryType == 'global') {
                $slug = Arr::get($item, 'global_inventory');
                if (!$slug) {
                    continue;
                }
                if ($isPaymentInput) {
                    $selectedQuantity = $this->getQuantity($item['parent_input_name'], $this->formData);
                    if (!$selectedQuantity) {
                        throw new \Exception("continue");
                    }
                }
                if (Arr::exists($totalSelectedQuantity, $slug)) {
                    $totalSelectedQuantity[$slug] += $selectedQuantity;
                } else {
                    $totalSelectedQuantity[$slug] = $selectedQuantity;
                }
            }
        }
        //validate global inventory multiple options
        foreach ($totalSelectedQuantity as $slug => $totalSelected) {
            $usedQuantity = Arr::get($this->usedGlobalItemsRecords, $slug);
            $availableQuantity = intval(Arr::get($this->globalInventoryItems, $slug . '.quantity'));
            $this->validateStockOut($usedQuantity + $totalSelected, $availableQuantity);
        }
    }

    /**
     * @throws \Exception
     */
    private function validateStockOut($used, $available)
    {
        if ($used > $available) {
            throw new \Exception("stock-out");
        }
    }
    
    private function isEmpty($key)
    {
        if (!isset($this->formData[$key])) {
            throw new \Exception("continue");
        }
    }
    
    private static function runSumQuantityQuery($formId)
    {
        global $wpdb;
        $quantity = wpFluent()->table('fluentform_order_items')
            ->select([
                'fluentform_order_items.item_name',
                'fluentform_order_items.item_price',
                'fluentform_order_items.quantity',
                'fluentform_order_items.parent_holder',
                wpFluent()->raw('sum(' . $wpdb->prefix . 'fluentform_order_items.quantity) as total_count')
            ])
            ->where('fluentform_order_items.form_id', $formId)
            ->groupBy('fluentform_order_items.item_name')
            ->groupBy('fluentform_order_items.item_price')
            ->groupBy('fluentform_order_items.parent_holder')
            ->where('fluentform_submissions.payment_status', '!=', 'refunded')
            ->where('fluentform_submissions.status', '!=', 'trashed')
            ->where('fluentform_submissions.status', '!=', 'declined')
            ->rightJoin('fluentform_submissions', 'fluentform_submissions.id', '=',
                'fluentform_order_items.submission_id')
            ->get();
        return (array)$quantity;
    }
    
    public static function getValidationMessage($item)
    {
        $stockOutMsg = sanitize_text_field(Arr::get($item, 'settings.inventory_stockout_message'));
        $isPaymentInput = Arr::get($item, 'settings.is_payment_field') == 'yes';
        $inputType = Arr::get($item, 'attributes.type') ? Arr::get($item, 'attributes.type') : Arr::get($item,
            'element');
        return array($stockOutMsg, $isPaymentInput, $inputType);
    }
    
    /**
     * @param $form
     * @return array
     *        # Array Format
     *        $usedGlobalItemsRecords = [
     *            'inventory-item-name' => [
     *                'multiple-option-input-name'  => [
     *                    'option_name' => count
     *                ],
     *                'single-option-input-name' => count
     *            ]
     *        ];
     */
    public function usedGlobalInventoryItemCount()
    {
        $usedGlobalInventoryItems = $this->usedGlobalInventories();
        if (empty($usedGlobalInventoryItems)) {
            return [];
        }
        return self::calculateGlobalInventory($usedGlobalInventoryItems);
    }
    
    public function usedGlobalInventories()
    {
        $this->globalInventoryItems = get_option('ff_inventory_list');
        
        if (!is_array($this->globalInventoryItems)) {
            return [];
        }
        
        $globalInventoryField = InventoryFieldsRenderer::getInventoryFields($this->form, ['global']);
        if (empty($globalInventoryField)) {
            return [];
        }
        $inventoryFields = [];
        $inventorySlugs = [];
        foreach ($globalInventoryField as $fieldName => $field) {
            if ($this->isSingleInventoryField($field)) {
                $slug = Arr::get($globalInventoryField,$fieldName . '.settings.global_inventory');
                $attachedGlobalInventorySlug = $slug;
                $inventorySlugs[] = $slug;
            } else {
                $options = $this->getOptions($field);
                $isPaymentField = $this->isPaymentField($field);
                $attachedGlobalInventorySlug = [];
                foreach ($options as $option) {
                    $key = $isPaymentField ? 'label' : 'value';
                    $slug = Arr::get($option, 'global_inventory');
                    $attachedGlobalInventorySlug[Arr::get($option, $key)] = $slug;
                    $inventorySlugs[] = $slug;
                }
            }
            $inventoryFields[$fieldName] = $attachedGlobalInventorySlug;
        }
        $this->globalInventoryFieldsName = $inventoryFields;
        return self::getSubmittedGlobalInventories(array_filter(array_unique($inventorySlugs)));
    }

    public function getGlobalInventoryInfo($slug)
    {
        if (!$this->usedGlobalItemsRecords) {
            $this->usedGlobalItemsRecords = $this->usedGlobalInventoryItemCount();
        }
        $quantity = (int)Arr::get($this->globalInventoryItems, $slug . '.quantity');
        $usedQuantity = (int)Arr::get($this->usedGlobalItemsRecords, $slug, 0);
        return [$quantity, $usedQuantity];
    }

    public function isSingleInventoryField($field)
    {
        return 'single' == Arr::get($field, 'attributes.type');
    }

    public function getOptions($field)
    {
        $optionKey = $this->isPaymentField($field) ? 'pricing_options' : 'advanced_options';
        return Arr::get($field, 'settings.' . $optionKey, []);
    }

    public function isPaymentField($field)
    {
        return 'yes' == Arr::get($field, 'settings.is_payment_field');
    }
    
    public static function getSubmittedGlobalInventories($usedInventoryFields)
    {
        return SubmissionMeta::select(['name', 'value'])
            ->where('meta_key', 'ff_used_global_inventory_item')
            ->where(function($query) {
                $query->where('status', '!=', 'refunded')
                    ->orWhereNull('status');
            })
            ->whereIn('name', $usedInventoryFields)
            ->with(['form' => function ($query) {
                $query->select(['title']);
            }])
            ->get()->transform(function ($item) {
                $item->value = json_decode($item->value, true);
                return $item;
            });
    }
    
    public static function calculateGlobalInventory($usedGlobalInventoryItems, $asFormat = false)
    {
        $usedGlobalItemsRecords = [];
        foreach ($usedGlobalInventoryItems as $item) {
            $name = $item->name;
            $value = $item->value['value'];
            $quantity = $item->value['quantity'];
            if (is_string($value)) {
                self::formatRecords($usedGlobalItemsRecords, $name, $value, $quantity, $asFormat);
            } else {
                foreach ($value as $subItem) {
                    self::formatRecords($usedGlobalItemsRecords, $name, $subItem, $quantity, $asFormat);
                }
            }
        }
       
        return $usedGlobalItemsRecords;
    }

    private static function formatRecords(&$usedGlobalItemsRecords, $name, $subItem, $quantity, $asFormat)
    {
        if ($asFormat) {
            $usedGlobalItemsRecords[$name][$subItem] = isset($usedGlobalItemsRecords[$name][$subItem]) ? $usedGlobalItemsRecords[$name][$subItem] + $quantity : $quantity;
        } else {
            $usedGlobalItemsRecords[$name] = isset($usedGlobalItemsRecords[$name]) ? $usedGlobalItemsRecords[$name] + $quantity : $quantity;
        }
    }
    
    private function validateSimpleInventory()
    {
        $inventoryFields = InventoryFieldsRenderer::getInventoryFields($this->form, ['simple']);
        if (empty($inventoryFields)) {
            return;
        }
        $statuses = ['read', 'unread', 'approved', 'unapproved', 'confirmed', 'unconfirmed'];
        $prevSubmissions = ReportHelper::getInputReport($this->form->id, array_keys($inventoryFields), $statuses);

        $errors = $this->processInventoryFields($inventoryFields, $prevSubmissions,'simple');
    
        if (!empty($errors)) {
            $errors = $this->applyValidationFilters($errors);
            wp_send_json(['errors' => $errors], 423);
        }
    
    }
    
    public function validateGlobalInventory()
    {
        $this->usedGlobalItemsRecords = $this->usedGlobalInventoryItemCount();
        $globalInventoryFields = InventoryFieldsRenderer::getInventoryFields($this->form, ['global']);
        $errors = $this->processInventoryFields($globalInventoryFields, $this->usedGlobalItemsRecords, 'global');
        if (!empty($errors)) {
            $errors = $this->applyValidationFilters($errors);
            wp_send_json(['errors' => $errors], 423);
        }
    }
    
    private function processInventoryFields($inventoryFields, $prevSubmissions, $inventoryType)
    {
        $errors = [];

        foreach ($inventoryFields as $fieldName => $field) {
            list($stockOutMsg, $isPaymentInput, $inputType) = self::getValidationMessage($field);
            
            try {
                $this->isEmpty($fieldName);
                
                if ($inputType == 'single') {
                    $this->handleSinglePaymentInput($fieldName, $field, $inventoryType);
                } elseif ($inputType == 'radio' || $inputType == 'select') {
                    $this->handleRadioSelect($fieldName, $field, $prevSubmissions, $isPaymentInput, $inventoryType);
                } elseif ($inputType == 'checkbox') {
                    $this->handleCheckbox($fieldName, $field, $prevSubmissions, $isPaymentInput, $inventoryType);
                }
            } catch (\Exception $e) {
                if ($e->getMessage() == 'continue') {
                    continue;
                } elseif ($e->getMessage() == 'stock-out') {
                    $inventoryMessages = [
                        'out_of_stock' => $stockOutMsg,
                        'insufficient_stock' => __('Insufficient stock available', 'fluentformpro'),
                        'stock_limit_reached' => __('Stock limit has been reached', 'fluentformpro')
                    ];
                    $inventoryMessages = apply_filters('fluentform/inventory_field_messages', $inventoryMessages, $this->form);

                    $errors[$fieldName] = [
                        'stock-out' => wpFluentForm()->applyFilters('fluentform/inventory_validation_error', $inventoryMessages['out_of_stock'], $fieldName, $field, $this->formData, $this->form)
                    ];
                   
                    break;
                }
            }
        }
        return $errors;
    }
    
    private function applyValidationFilters($errors)
    {
        $app = wpFluentForm();
        $fields = FormFieldsParser::getInputs($this->form, ['rules', 'raw']);
        
        $errors = apply_filters_deprecated(
            'fluentform_validation_error',
            [$errors, $this->form, $fields, $this->formData],
            FLUENTFORM_FRAMEWORK_UPGRADE,
            'fluentform/validation_error',
            'Use fluentform/validation_error instead of fluentform_validation_error.'
        );
    
        return $app->applyFilters('fluentform/validation_error', $errors, $this->form, $fields, $this->formData);
    }
    
}
