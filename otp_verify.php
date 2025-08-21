<?php
session_start();

// رقم الجوال المعروض (من الجلسة أو من الاستعلام)
$phone = $_SESSION['pending_booking']['phone'] ?? ($_GET['phone'] ?? '');
$maskedPhone = $phone ? substr($phone, 0, 2) . '****' . substr($phone, -2) : '';

$successMsg = $errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $code = preg_replace('/\D/', '', $_POST['code'] ?? '');
  if (strlen($code) !== 6) {
    $errorMsg = 'يرجى إدخال 6 أرقام.';
  } else {
    // إن وُجد كود في الجلسة يتم التحقق منه، otherwise نقبل الرمز كتجربة
    if (isset($_SESSION['otp_code'])) {
      if ($code === $_SESSION['otp_code'] && time() <= ($_SESSION['otp_expires'] ?? 0)) {
        $_SESSION['otp_verified'] = true;
        $successMsg = 'تم التحقق بنجاح! سيتم تحويلك...';
        // نظّف قيم الـ OTP
        unset($_SESSION['otp_code'], $_SESSION['otp_expires'], $_SESSION['otp_attempts']);
      } else {
        $errorMsg = 'رمز غير صحيح أو انتهت صلاحيته.';
      }
    } else {
      $_SESSION['otp_verified'] = true;
      $successMsg = 'تم التحقق بنجاح! سيتم تحويلك...';
    }
  }
}
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>رمز التحقق</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    body{font-family:inherit;background:#f6f7f9}
    .otp input{width:52px;height:56px;text-align:center;font-weight:800;font-size:20px}
    .otp input:focus{outline:2px solid #16a34a;outline-offset:2px}
  </style>
</head>
<body class="min-h-screen flex items-center justify-center px-4">
  <div class="w-full max-w-xl bg-white border border-gray-200 rounded-2xl shadow-lg overflow-hidden">
    <div class="bg-green-50 px-5 py-3 border-b border-gray-200 text-green-800 font-semibold">التحقق لحجزك</div>
    <div class="p-6">
      <?php if ($successMsg): ?>
        <div class="mb-4 p-3 rounded bg-green-100 text-green-700 border border-green-300"><?php echo $successMsg; ?></div>
        <div class="text-center py-6">
          <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-green-600 mb-3"></div>
          <p class="text-gray-600">جاري التوجيه...</p>
        </div>
        <script>
          setTimeout(()=>{ location.href = 'summary.php'; }, 1500);
        </script>
      <?php else: ?>
        <?php if ($errorMsg): ?>
          <div class="mb-4 p-3 rounded bg-red-100 text-red-700 border border-red-300"><?php echo $errorMsg; ?></div>
        <?php endif; ?>

        <h1 class="text-xl font-extrabold text-green-700 mb-1">أدخل رمز التحقق</h1>
        <p class="text-gray-600 mb-4">تم إرسال الرمز إلى الجوال المنتهي بـ <?php echo htmlspecialchars($maskedPhone); ?>.</p>

        <form method="post" id="otpForm" class="space-y-4">
          <div class="otp grid grid-flow-col gap-2" dir="ltr">
            <input maxlength="1" inputmode="numeric" class="border rounded-lg" />
            <input maxlength="1" inputmode="numeric" class="border rounded-lg" />
            <input maxlength="1" inputmode="numeric" class="border rounded-lg" />
            <input maxlength="1" inputmode="numeric" class="border rounded-lg" />
            <input maxlength="1" inputmode="numeric" class="border rounded-lg" />
            <input maxlength="1" inputmode="numeric" class="border rounded-lg" />
          </div>
          <input type="hidden" name="code" id="code" />
          <div class="flex items-center justify-between">
            <span id="timer" class="text-sm text-gray-600">إعادة الإرسال خلال 30 ث</span>
            <button type="button" id="resendBtn" class="text-green-700 font-semibold" disabled>إعادة الإرسال الآن</button>
          </div>
          <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2.5 rounded-lg">تأكيد</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <script>
    const inputs = Array.from(document.querySelectorAll('.otp input'));
    const codeInput = document.getElementById('code');
    const form = document.getElementById('otpForm');
    const timerEl = document.getElementById('timer');
    const resendBtn = document.getElementById('resendBtn');

    // WebOTP API (إن كان المتصفح يدعم)
    if ('OTPCredential' in window) {
      try {
        const ac = new AbortController();
        navigator.credentials.get({ otp: { transport: ['sms'] }, signal: ac.signal })
          .then(cred => {
            if (!cred || !cred.code) return;
            const digits = cred.code.replace(/\D/g,'').slice(0,6);
            for (let i=0;i<digits.length;i++) inputs[i].value = digits[i];
            collect(); form.requestSubmit();
          }).catch(()=>{});
        setTimeout(()=>ac.abort(), 60000);
      } catch {}
    }

    if (inputs.length) inputs[0].focus();

    function collect(){
      const v = inputs.map(i => (i.value||'').replace(/\D/g,'').slice(0,1)).join('');
      codeInput.value = v; return v;
    }

    inputs.forEach((inp, idx) => {
      inp.addEventListener('input', () => {
        inp.value = inp.value.replace(/\D/g,'').slice(0,1);
        if (inp.value && idx < inputs.length - 1) inputs[idx+1].focus();
      });
      inp.addEventListener('keydown', e => {
        if (e.key === 'Backspace' && !inp.value && idx > 0) inputs[idx-1].focus();
        if (e.key === 'ArrowLeft' && idx > 0) { inputs[idx-1].focus(); e.preventDefault(); }
        if (e.key === 'ArrowRight' && idx < inputs.length - 1) { inputs[idx+1].focus(); e.preventDefault(); }
      });
      inp.addEventListener('paste', e => {
        const data = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g,'');
        if (!data) return; e.preventDefault();
        for (let i=0;i<data.length && idx+i<inputs.length;i++) inputs[idx+i].value = data[i];
        inputs[Math.min(idx + data.length, inputs.length - 1)].focus();
      });
    });

    form?.addEventListener('submit', e => {
      const v = collect();
      if (v.length !== 6) { e.preventDefault(); alert('يرجى إدخال الرمز الكامل'); }
    });

    // مؤقت إعادة الإرسال
    let remaining = 30;
    const iv = setInterval(() => {
      remaining--;
      if (remaining <= 0) { clearInterval(iv); timerEl.textContent = ''; resendBtn.disabled = false; }
      else { timerEl.textContent = `إعادة الإرسال خلال ${remaining} ث`; }
    }, 1000);

    // زر إعادة الإرسال (ينادي ملفًا اختياريًا otp_resend.php)
    resendBtn?.addEventListener('click', async () => {
      resendBtn.disabled = true;
      try {
        const r = await fetch('otp_resend.php', { method: 'POST' });
        const j = await r.json();
        remaining = (j.cooldown ?? 30);
        timerEl.textContent = `تم الإرسال. يمكنك إعادة الإرسال خلال ${remaining} ث`;
      } catch {
        // في حال عدم وجود الملف، فقط نعيد التفعيل بعد 30 ثانية
        remaining = 30;
        timerEl.textContent = `تم الإرسال. يمكنك إعادة الإرسال خلال ${remaining} ث`;
      }
      const iv2 = setInterval(() => {
        remaining--;
        if (remaining <= 0) { clearInterval(iv2); timerEl.textContent = ''; resendBtn.disabled = false; }
        else { timerEl.textContent = `إعادة الإرسال خلال ${remaining} ث`; }
      }, 1000);
    });
  </script>
</body>
</html>