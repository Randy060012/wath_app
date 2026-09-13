@extends('errors.layout')

@php $code = '419'; $icon = 'hourglass'; $title = 'Session expirée'; @endphp

@section('message', 'Votre session a expiré. Revenez en arrière puis rechargez la page.')
