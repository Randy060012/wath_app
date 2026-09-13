@extends('errors.layout')

@php $code = '500'; $icon = 'triangle-alert'; $title = 'Erreur serveur'; @endphp

@section('message', 'Une erreur inattendue est survenue. Réessayez ou contactez l\'administrateur.')
