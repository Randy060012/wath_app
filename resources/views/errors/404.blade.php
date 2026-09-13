@extends('errors.layout')

@php $code = '404'; $icon = 'search-x'; $title = 'Page introuvable'; @endphp

@section('message', "Cette page n'existe pas ou a été déplacée.")
