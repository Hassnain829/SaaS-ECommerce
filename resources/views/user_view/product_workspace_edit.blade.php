@extends('layouts.user.user-sidebar')

@php
    $wizardKind = 'edit';
    $wizardProductPayload = $editProductPayload;
@endphp

@include('user_view.partials.product_wizard_page')
