<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Google OAuth Callback</title>
</head>
<body>
    <div style="text-align: center; padding: 50px; font-family: Arial, sans-serif;">
        <h3>✅ Xác thực thành công!</h3>
        <p>Sẽ đóng popup trong: <span id="countdown" style="font-weight: bold; font-size: 18px; color: blue;">3</span> giây</p>
    </div>

    <script>
        console.log('Callback page loaded!');

        // Get URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        const code = urlParams.get('code');
        const state = urlParams.get('state');
        const error = urlParams.get('error');

        console.log('Callback params:', { code, state, error });

        if (error) {
            console.log('OAuth failed with error:', error);
        } else if (code) {
            console.log('OAuth successful! Code received.');
        } else {
            console.log('No code or error found in callback URL');
        }

        // DEBUG: Countdown timer and delay
        let countdown = 3;
        const countdownEl = document.getElementById('countdown');

        const timer = setInterval(() => {
            countdown--;
            if (countdownEl) countdownEl.textContent = countdown;

            if (countdown <= 0) {
                clearInterval(timer);
                window.close();
            }
        }, 1000);
    </script>
</body>
</html>
