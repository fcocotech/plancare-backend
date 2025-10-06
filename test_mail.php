<?php
// Simple test to check if we can resolve smtp.gmail.com
echo "Testing DNS resolution for smtp.gmail.com...\n";

$host = 'smtp.gmail.com';
$ip = gethostbyname($host);

if ($ip === $host) {
    echo "❌ Cannot resolve smtp.gmail.com - DNS resolution failed\n";
    echo "This is the cause of the email sending errors.\n";
    echo "\nSuggestions:\n";
    echo "1. Check DNS configuration on the server\n";
    echo "2. Verify internet connectivity\n";
    echo "3. Consider using a different SMTP provider\n";
    echo "4. Temporarily disable email sending by setting MAIL_ENABLED=false in .env\n";
} else {
    echo "✅ smtp.gmail.com resolves to: $ip\n";
    
    // Test if we can connect to the SMTP port
    echo "Testing SMTP connection to smtp.gmail.com:587...\n";
    $connection = @fsockopen('smtp.gmail.com', 587, $errno, $errstr, 10);
    
    if (!$connection) {
        echo "❌ Cannot connect to smtp.gmail.com:587 - $errstr ($errno)\n";
        echo "The SMTP port might be blocked or the service is unavailable.\n";
    } else {
        echo "✅ Successfully connected to smtp.gmail.com:587\n";
        fclose($connection);
    }
}
?>
