<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services Integration Test</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; max-width: 1200px; margin: 0 auto; }
        .test-section { margin: 30px 0; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
        .test-section h2 { margin-top: 0; color: #333; }
        .step { margin: 15px 0; padding: 15px; background: #f5f5f5; border-left: 4px solid #2563eb; }
        .step h3 { margin-top: 0; }
        button { padding: 10px 20px; background: #2563eb; color: white; border: none; border-radius: 6px; cursor: pointer; }
        button:hover { background: #1d4ed8; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Services Integration Test</h1>
    
    <div class="test-section">
        <h2>Test 1: API Returns Services</h2>
        <div id="apiTestResult">Testing...</div>
    </div>

    <div class="test-section">
        <h2>Complete Workflow Test</h2>
        
        <div class="step">
            <h3>Step 1: Check Database</h3>
            <p>Verify services table has active services:</p>
            <button onclick="testDatabase()">Check Database</button>
            <div id="dbTestResult"></div>
        </div>

        <div class="step">
            <h3>Step 2: Test API</h3>
            <p>Verify api_public_services.php returns services:</p>
            <button onclick="testAPI()">Test API</button>
            <div id="apiTestResult2"></div>
        </div>

        <div class="step">
            <h3>Step 3: Admin Add Service</h3>
            <p>Add a new service via admin panel:</p>
            <a href="admin_services.php" target="_blank" style="display: inline-block; padding: 10px 20px; background: #2563eb; color: white; text-decoration: none; border-radius: 6px;">Open Admin Services</a>
            <p style="font-size: 0.9em; color: #666; margin-top: 10px;">Add a test service there, then return here and test again.</p>
        </div>

        <div class="step">
            <h3>Step 4: Staff Load Services</h3>
            <p>Open staff admission page to see if services load:</p>
            <a href="staff_new_admission.php" target="_blank" style="display: inline-block; padding: 10px 20px; background: #10b981; color: white; text-decoration: none; border-radius: 6px;">Open Staff Admission</a>
            <p style="font-size: 0.9em; color: #666; margin-top: 10px;">Services should display automatically. If not, click "Refresh Services" button.</p>
        </div>
    </div>

    <div class="test-section">
        <h2>Troubleshooting</h2>
        <ul style="line-height: 1.8;">
            <li><strong>API Error:</strong> Check if api_public_services.php exists in root folder</li>
            <li><strong>No Services:</strong> Check if services are set to "Active" status in admin</li>
            <li><strong>Staff Not Loading:</strong> Open browser console (F12) and check for errors</li>
            <li><strong>Need Refresh:</strong> After adding service in admin, refresh staff admission page</li>
        </ul>
    </div>

    <script>
        // Test 1: Check API on load
        fetch('api_public_services.php')
            .then(r => r.json())
            .then(data => {
                const result = document.getElementById('apiTestResult');
                if (data.success) {
                    result.innerHTML = '<span class="success">✓ API Working! Found ' + data.services.length + ' active services</span>';
                    result.innerHTML += '<br><br><strong>Services:</strong><br>' + 
                        data.services.map(s => s.name + ' (' + s.mode + ') - ₱' + s.price).join('<br>');
                } else {
                    result.innerHTML = '<span class="error">✗ API Error: ' + data.message + '</span>';
                }
            })
            .catch(e => {
                document.getElementById('apiTestResult').innerHTML = '<span class="error">✗ Failed to connect to API: ' + e.message + '</span>';
            });

        // Test Database
        function testDatabase() {
            const result = document.getElementById('dbTestResult');
            result.innerHTML = 'Checking...';
            
            fetch('api_public_services.php')
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.services.length > 0) {
                        result.innerHTML = '<span class="success">✓ Database has ' + data.services.length + ' active services</span>';
                    } else if (data.success && data.services.length === 0) {
                        result.innerHTML = '<span class="error">✗ No active services found. Add some in admin panel.</span>';
                    } else {
                        result.innerHTML = '<span class="error">✗ Error: ' + data.message + '</span>';
                    }
                })
                .catch(e => {
                    result.innerHTML = '<span class="error">✗ Error: ' + e.message + '</span>';
                });
        }

        // Test API
        function testAPI() {
            const result = document.getElementById('apiTestResult2');
            result.innerHTML = 'Testing API...';
            
            fetch('api_public_services.php')
                .then(r => {
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.json();
                })
                .then(data => {
                    if (data.success) {
                        result.innerHTML = '<span class="success">✓ API Working! Returns JSON data</span>';
                    } else {
                        result.innerHTML = '<span class="error">✗ API returned error: ' + data.message + '</span>';
                    }
                })
                .catch(e => {
                    result.innerHTML = '<span class="error">✗ API Error: ' + e.message + '</span>';
                });
        }
    </script>
</body>
</html>
