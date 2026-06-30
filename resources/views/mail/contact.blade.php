<!DOCTYPE html>
<html>
<head>
    <title>New Contact Inquiry</title>
</head>
<body>
    <h2>Contact Form Submission</h2>
    
    <p><strong>Name:</strong> {{ $data['firstName'] }} {{ $data['lastName'] }}</p>
    <p><strong>Email:</strong> {{ $data['email'] }}</p>
    <p><strong>Phone:</strong> {{ $data['phone'] }}</p>
    
    <p><strong>Message:</strong></p>
    <p>{{ $data['message'] }}</p>
</body>
</html>