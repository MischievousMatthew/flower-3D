@extends('emails.layouts.bloomcraft')

@section('title', 'Updated application to review')
@section('preheader', ($application->store_name ?: $application->owner_name) . ' sent the updates you asked for.')
@section('eyebrow', 'For the admin team')
@section('heading', 'Updates are in')

@section('content')
    <p style="margin:0 0 6px;font-size:15px;line-height:1.7;">
        <strong style="color:#363F4E;">{{ $application->store_name ?: $application->owner_name }}</strong>
        sent the updated details you asked for. Their application is ready for another look.
    </p>

    @include('emails.partials.panel', [
        'tone' => 'plain',
        'label' => 'Applicant',
        'html' => e($application->owner_name ?: '-') . '<br><span style="color:#6b7380;">' . e($application->email) . '</span>',
    ])

    <p style="margin:0;font-size:14.5px;line-height:1.7;">
        Open <strong style="color:#363F4E;">Vendor requests</strong> in the admin panel to review what changed.
    </p>
@endsection
