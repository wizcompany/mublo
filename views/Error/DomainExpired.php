<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>서비스 만료 안내</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background-color: #f8f9fa; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .container { text-align: center; background: white; padding: 3rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); max-width: 500px; width: 90%; border-top: 4px solid #ecc94b; }
        h1 { color: #2d3748; font-size: 1.5rem; margin-bottom: 1rem; }
        p { color: #718096; line-height: 1.6; margin-bottom: 1.5rem; }
        .date { color: #e53e3e; font-weight: bold; }
        .btn { display: inline-block; background: #3182ce; color: white; padding: 0.75rem 1.5rem; text-decoration: none; border-radius: 6px; font-weight: 500; transition: background 0.2s; }
        .btn:hover { background: #2b6cb0; }
        .icon { font-size: 3rem; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">⏳</div>
        <h1>서비스 기간이 만료되었습니다</h1>
        <p>
            해당 사이트의 서비스 이용 기간이<br>
            <span class="date"><?= htmlspecialchars($expireDate ?? '알 수 없음') ?></span> 부로 종료되었습니다.
        </p>
        <p>
            서비스 연장 및 데이터 백업 관련 문의는<br>
            관리자에게 연락해 주시기 바랍니다.
        </p>
    </div>
</body>
</html>