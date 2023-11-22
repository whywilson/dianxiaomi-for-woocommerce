<?php
/**
 * Created by PhpStorm.
 * User: Sunny Chow
 * Date: 4/2/15
 * Time: 6:08 PM
 */

$dianxiaomi_fields = array(
    'dianxiaomi_tracking_provider_name' => array(
        'id' => 'dianxiaomi_tracking_provider_name',
        'type' => 'text',
        'label' => '',
        'placeholder' => '',
        'description' => '',
        'class' => 'hidden'
    ),

    'dianxiaomi_tracking_required_fields' => array(
        'id' => 'dianxiaomi_tracking_required_fields',
        'type' => 'text',
        'label' => '',
        'placeholder' => '',
        'description' => '',
        'class' => 'hidden'
    ),

    'dianxiaomi_tracking_number' => array(
        'id' => 'dianxiaomi_tracking_number',
        'type' => 'text',
        'label' => 'Tracking number',
        'placeholder' => '',
        'description' => '',
        'class' => ''
    ),

    'dianxiaomi_tracking_shipdate' => array(
        'key' => 'tracking_ship_date',
        'id' => 'dianxiaomi_tracking_shipdate',
        'type' => 'date',
        'label' => 'Date shipped',
        'placeholder' => 'YYYY-MM-DD',
        'description' => '',
        'class' => 'date-picker-field hidden-field'
    ),

    'dianxiaomi_tracking_postal' => array(
        'key' => 'tracking_postal_code',
        'id' => 'dianxiaomi_tracking_postal',
        'type' => 'text',
        'label' => 'Postal Code',
        'placeholder' => '',
        'description' => '',
        'class' => 'hidden-field'
    ),

    'dianxiaomi_tracking_account' => array(
        'key' => 'tracking_account_number',
        'id' => 'dianxiaomi_tracking_account',
        'type' => 'text',
        'label' => 'Account name',
        'placeholder' => '',
        'description' => '',
        'class' => 'hidden-field'
    ),

    'dianxiaomi_tracking_key' => array(
        'key' => 'tracking_key',
        'id' => 'dianxiaomi_tracking_key',
        'type' => 'text',
        'label' => 'Tracking key',
        'placeholder' => '',
        'description' => '',
        'class' => 'hidden-field'
    ),

    'dianxiaomi_tracking_destination_country' => array(
        'key' => 'tracking_destination_country',
        'id' => 'dianxiaomi_tracking_destination_country',
        'type' => 'text',
        'label' => 'Destination Country',
        'placeholder' => '',
        'description' => '',
        'class' => 'hidden-field'
    )
);
