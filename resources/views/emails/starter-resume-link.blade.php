<p>Hello{{ $submission->first_name ? ' '.$submission->first_name : '' }},</p>

@if ($isActive)
    <p>Here is your secure link to your Festilaw file. Your Creator Pack is active · you can view it and download your signed mandate and documents:</p>

    <p><a href="{{ $resumeUrl }}">Open my file</a></p>

    <p>Keep this link private: anyone who has it can access your file. Your reference is <strong>{{ $submission->reference }}</strong>.</p>
@else
    <p>Here is your secure link to continue your Festilaw application (Creator Pack), right where you left off:</p>

    <p><a href="{{ $resumeUrl }}">Continue my application</a></p>

    <p>Keep this link private: anyone who has it can access your file. Your reference is <strong>{{ $submission->reference }}</strong>, and the link stays valid for {{ $ttlDays }} days.</p>
@endif

<p>If you didn't start an application with Festilaw, you can safely ignore this email.</p>
