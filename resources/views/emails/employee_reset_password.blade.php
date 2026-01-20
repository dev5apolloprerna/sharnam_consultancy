<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Reset Password</title>
</head>
<body style="font-family:Arial,sans-serif;">
<p>Hello {{ $employee->employee_name ?? 'Employee' }},</p>

<p>Your temporary password is:</p>

<h2 style="letter-spacing:1px;">{{ $tempPassword }}</h2>

<p>This temporary password is valid for {{ $minutes }} minutes.</p>

<p>Please login using this temporary password and immediately set a new password.</p>

<p>Thanks,<br>{{ config('app.name') }}</p>
</body>
</html>
