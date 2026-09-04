@extends('layouts.user.user-sidebar')

@php
    $wizardKind = 'create';
    $wizardProductPayload = $createProductPayload;
@endphp

@include('user_view.partials.product_wizard_page')
