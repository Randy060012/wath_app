@extends('errors.layout')

@php $code = '403'; $icon = 'lock'; $title = 'Accès refusé'; @endphp

@section('message', "Votre rôle n'autorise pas l'accès à cet écran.")
