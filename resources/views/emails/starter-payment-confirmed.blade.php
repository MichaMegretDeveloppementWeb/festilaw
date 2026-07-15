<p>Hello{{ $submission->first_name ? ' '.$submission->first_name : '' }},</p>

<p>Your payment is confirmed and your Festilaw Creator Pack is now active. We'll issue your official EU Responsible Person address and email it to you within 24 hours.</p>

<p>Your reference is <strong>{{ $submission->reference }}</strong>.</p>

<p>Thank you for choosing Festilaw.</p>
