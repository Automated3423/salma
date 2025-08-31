<?php
session_start();

// CSRF Protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Rate limiting
if (!isset($_SESSION['otp_attempts'])) {
    $_SESSION['otp_attempts'] = 0;
    $_SESSION['otp_last_attempt'] = 0;
}

// Check if OTP verification is required
if (!isset($_SESSION['otp_code']) || !isset($_SESSION['pending_booking'])) {
    header('Location: index.php');
    exit;
}

// رقم الجوال المعروض (من الجلسة أو من الاستعلام)
$phone = $_SESSION['pending_booking']['phone'] ?? '';
$maskedPhone = $phone ? substr($phone, 0, 2) . '****' . substr($phone, -2) : '';

$successMsg = $errorMsg = '';

// Check rate limiting (max 5 attempts per minute)
$timeSinceLastAttempt = time() - $_SESSION['otp_last_attempt'];
if ($_SESSION['otp_attempts'] >= 5 && $timeSinceLastAttempt < 60) {
    $errorMsg = 'تم تجاوز عدد المحاولات المسموح. يرجى الانتظار دقيقة.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errorMsg = 'خطأ في الأمان. يرجى المحاولة مرة أخرى.';
    } else {
        $code = preg_replace('/\D/', '', $_POST['code'] ?? '');
        
        if (strlen($code) !== 6) {
            $errorMsg = 'يرجى إدخال 6 أرقام.';
        } else {
            $_SESSION['otp_attempts']++;
            $_SESSION['otp_last_attempt'] = time();
            
            // Check OTP validity
            if ($code === $_SESSION['otp_code'] && time() <= ($_SESSION['otp_expires'] ?? 0)) {
                // Success - regenerate session ID for security
                session_regenerate_id(true);
                $_SESSION['otp_verified'] = true;
                $successMsg = 'تم التحقق بنجاح! سيتم تحويلك...';
                
                // Clean up OTP data
                unset($_SESSION['otp_code'], $_SESSION['otp_expires'], $_SESSION['otp_attempts'], $_SESSION['otp_last_attempt']);
            } else {
                $attemptsLeft = max(0, 5 - $_SESSION['otp_attempts']);
                if (time() > ($_SESSION['otp_expires'] ?? 0)) {
                    $errorMsg = 'انتهت صلاحية الرمز. يرجى طلب رمز جديد.';
                } else {
                    $errorMsg = "رمز غير صحيح. المحاولات المتبقية: $attemptsLeft";
                }
            }
        }
    }
}

// Calculate remaining time for OTP
$remainingTime = max(0, ($_SESSION['otp_expires'] ?? 0) - time());
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
    .otp input[aria-invalid="true"]{border-color:#dc2626}
    .otp input:disabled{background-color:#f3f4f6;cursor:not-allowed}
  </style>
</head>
<body class="min-h-screen flex items-center justify-center px-4">
  <div class="w-full max-w-xl bg-white border border-gray-200 rounded-2xl shadow-lg overflow-hidden">
    <div class="bg-green-50 px-5 py-3 border-b border-gray-200 text-green-800 font-semibold">التحقق لحجزك</div>
    <div class="p-6">
      <?php if ($successMsg): ?>
        <div class="mb-4 p-3 rounded bg-green-100 text-green-700 border border-green-300" role="alert">
          <svg class="inline w-5 h-5 ml-2" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
          </svg>
          <?php echo htmlspecialchars($successMsg); ?>
        </div>
        <div class="text-center py-6">
          <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-green-600 mb-3"></div>
          <p class="text-gray-600">جاري التوجيه...</p>
        </div>
        <script>
          setTimeout(()=>{ location.href = 'summary.php'; }, 1500);
        </script>
      <?php else: ?>
        <?php if ($errorMsg): ?>
          <div class="mb-4 p-3 rounded bg-red-100 text-red-700 border border-red-300" role="alert">
            <svg class="inline w-5 h-5 ml-2" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
            </svg>
            <?php echo htmlspecialchars($errorMsg); ?>
          </div>
        <?php endif; ?>

        <h1 class="text-xl font-extrabold text-green-700 mb-1">أدخل رمز التحقق</h1>
        <p class="text-gray-600 mb-4">تم إرسال الرمز إلى الجوال المنتهي بـ <?php echo htmlspecialchars($maskedPhone); ?>.</p>
        
        <?php if ($remainingTime > 0): ?>
          <p class="text-sm text-gray-500 mb-4">الرمز صالح لمدة <span id="otpTimer"><?php echo $remainingTime; ?></span> ثانية</p>
        <?php else: ?>
          <p class="text-sm text-red-600 mb-4">انتهت صلاحية الرمز. يرجى طلب رمز جديد.</p>
        <?php endif; ?>

        <form method="post" id="otpForm" class="space-y-4" novalidate>
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>" />
          <div class="otp grid grid-flow-col gap-2" dir="ltr" role="group" aria-label="رمز التحقق المكون من 6 أرقام">
            <?php for($i = 0; $i < 6; $i++): ?>
              <input 
                type="text" 
                maxlength="1" 
                inputmode="numeric" 
                pattern="[0-9]" 
                class="border rounded-lg transition-colors"
                aria-label="الرقم <?php echo $i + 1; ?>"
                <?php echo $_SESSION['otp_attempts'] >= 5 && $timeSinceLastAttempt < 60 ? 'disabled' : ''; ?>
              />
            <?php endfor; ?>
          </div>
          <input type="hidden" name="code" id="code" />
          
          <div class="flex items-center justify-between">
            <span id="timer" class="text-sm text-gray-600">إعادة الإرسال خلال 30 ث</span>
            <button type="button" id="resendBtn" class="text-green-700 font-semibold hover:text-green-800 disabled:text-gray-400" disabled>إعادة الإرسال الآن</button>
          </div>
          
          <button 
            type="submit" 
            class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2.5 rounded-lg transition-colors disabled:bg-gray-400"
            <?php echo $_SESSION['otp_attempts'] >= 5 && $timeSinceLastAttempt < 60 ? 'disabled' : ''; ?>
          >
            تأكيد
          </button>
        </form>
        
        <div class="mt-4 text-center">
          <a href="index.php" class="text-sm text-gray-600 hover:text-gray-800">العودة للصفحة الرئيسية</a>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <script>
    const inputs = Array.from(document.querySelectorAll('.otp input'));
    const codeInput = document.getElementById('code');
    const form = document.getElementById('otpForm');
    const timerEl = document.getElementById('timer');
    const resendBtn = document.getElementById('resendBtn');
    const otpTimerEl = document.getElementById('otpTimer');

    // WebOTP API (إن كان المتصفح يدعم)
    if ('OTPCredential' in window) {
      try {
        const ac = new AbortController();
        navigator.credentials.get({ otp: { transport: ['sms'] }, signal: ac.signal })
          .then(cred => {
            if (!cred || !cred.code) return;
            const digits = cred.code.replace(/\D/g,'').slice(0,6);
            for (let i=0;i<digits.length;i++) {
              if (inputs[i] && !inputs[i].disabled) {
                inputs[i].value = digits[i];
              }
            }
            collect(); 
            if (!inputs[0].disabled) form.requestSubmit();
          }).catch(()=>{});
        setTimeout(()=>ac.abort(), 60000);
      } catch {}
    }

    // Auto-focus first empty input
    const firstEmpty = inputs.find(i => !i.value && !i.disabled);
    if (firstEmpty) firstEmpty.focus();
    else if (inputs[0] && !inputs[0].disabled) inputs[0].focus();

    function collect(){
      const v = inputs.map(i => (i.value||'').replace(/\D/g,'').slice(0,1)).join('');
      codeInput.value = v; 
      return v;
    }

    // Enhanced input handling
    inputs.forEach((inp, idx) => {
      inp.addEventListener('input', (e) => {
        // Only allow digits
        inp.value = inp.value.replace(/\D/g,'').slice(0,1);
        
        // Visual feedback
        if (inp.value) {
          inp.setAttribute('aria-invalid', 'false');
        }
        
        // Auto-advance to next input
        if (inp.value && idx < inputs.length - 1) {
          inputs[idx+1].focus();
        }
        
        // Auto-submit when all fields are filled
        if (idx === inputs.length - 1 && inp.value) {
          const code = collect();
          if (code.length === 6) {
            form.requestSubmit();
          }
        }
      });
      
      inp.addEventListener('keydown', e => {
        // Navigation with arrow keys and backspace
        if (e.key === 'Backspace' && !inp.value && idx > 0) {
          inputs[idx-1].focus();
          e.preventDefault();
        }
        if (e.key === 'ArrowLeft' && idx > 0) { 
          inputs[idx-1].focus(); 
          e.preventDefault(); 
        }
        if (e.key === 'ArrowRight' && idx < inputs.length - 1) { 
          inputs[idx+1].focus(); 
          e.preventDefault(); 
        }
      });
      
      // Paste handling
      inp.addEventListener('paste', e => {
        const data = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g,'');
        if (!data) return; 
        e.preventDefault();
        
        // Fill inputs starting from current position
        for (let i=0; i<data.length && idx+i<inputs.length; i++) {
          if (!inputs[idx+i].disabled) {
            inputs[idx+i].value = data[i];
          }
        }
        
        // Focus last filled input or last input
        const lastFilledIdx = Math.min(idx + data.length - 1, inputs.length - 1);
        inputs[lastFilledIdx].focus();
        
        // Auto-submit if all filled
        const code = collect();
        if (code.length === 6) {
          form.requestSubmit();
        }
      });
    });

    // Form validation
    form?.addEventListener('submit', e => {
      const v = collect();
      if (v.length !== 6) { 
        e.preventDefault(); 
        
        // Mark empty fields as invalid
        inputs.forEach((inp, idx) => {
          if (!inp.value) {
            inp.setAttribute('aria-invalid', 'true');
            inp.focus();
            return false;
          }
        });
        
        // Show error message
        const errorDiv = document.createElement('div');
        errorDiv.className = 'mb-4 p-3 rounded bg-yellow-100 text-yellow-700 border border-yellow-300';
        errorDiv.textContent = 'يرجى إدخال الرمز الكامل المكون من 6 أرقام';
        errorDiv.setAttribute('role', 'alert');
        form.insertBefore(errorDiv, form.firstChild);
        setTimeout(() => errorDiv.remove(), 3000);
      }
    });

    // Resend countdown timer
    let remaining = 30;
    const updateTimer = () => {
      remaining--;
      if (remaining <= 0) { 
        clearInterval(timerInterval); 
        timerEl.textContent = ''; 
        resendBtn.disabled = false; 
      } else { 
        timerEl.textContent = `إعادة الإرسال خلال ${remaining} ث`; 
      }
    };
    const timerInterval = setInterval(updateTimer, 1000);

    // OTP expiration countdown
    <?php if ($remainingTime > 0): ?>
    let otpRemaining = <?php echo $remainingTime; ?>;
    const otpInterval = setInterval(() => {
      otpRemaining--;
      if (otpRemaining <= 0) {
        clearInterval(otpInterval);
        if (otpTimerEl) {
          otpTimerEl.parentElement.textContent = 'انتهت صلاحية الرمز. يرجى طلب رمز جديد.';
          otpTimerEl.parentElement.className = 'text-sm text-red-600 mb-4';
        }
      } else if (otpTimerEl) {
        otpTimerEl.textContent = otpRemaining;
      }
    }, 1000);
    <?php endif; ?>

    // Resend button handler
    resendBtn?.addEventListener('click', async () => {
      resendBtn.disabled = true;
      
      // Show loading state
      const originalText = resendBtn.textContent;
      resendBtn.textContent = 'جاري الإرسال...';
      
      try {
        const r = await fetch('otp_resend.php', { 
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': '<?php echo $_SESSION['csrf_token']; ?>'
          },
          body: JSON.stringify({ phone: '<?php echo $phone; ?>' })
        });
        
        const j = await r.json();
        
        if (j.success) {
          remaining = (j.cooldown ?? 30);
          timerEl.textContent = `تم الإرسال. يمكنك إعادة الإرسال خلال ${remaining} ث`;
          
          // Show success message
          const successDiv = document.createElement('div');
          successDiv.className = 'mb-4 p-3 rounded bg-green-100 text-green-700 border border-green-300';
          successDiv.textContent = 'تم إرسال رمز جديد بنجاح';
          successDiv.setAttribute('role', 'alert');
          form.insertBefore(successDiv, form.firstChild);
          setTimeout(() => successDiv.remove(), 3000);
        } else {
          throw new Error(j.message || 'فشل إرسال الرمز');
        }
      } catch (error) {
        // Show error message
        const errorDiv = document.createElement('div');
        errorDiv.className = 'mb-4 p-3 rounded bg-red-100 text-red-700 border border-red-300';
        errorDiv.textContent = error.message || 'حدث خطأ في إرسال الرمز';
        errorDiv.setAttribute('role', 'alert');
        form.insertBefore(errorDiv, form.firstChild);
        setTimeout(() => errorDiv.remove(), 3000);
        
        // Reset button
        resendBtn.textContent = originalText;
        resendBtn.disabled = false;
        return;
      }
      
      // Reset button text
      resendBtn.textContent = originalText;
      
      // Start new countdown
      const iv2 = setInterval(() => {
        remaining--;
        if (remaining <= 0) { 
          clearInterval(iv2); 
          timerEl.textContent = ''; 
          resendBtn.disabled = false; 
        } else { 
          timerEl.textContent = `إعادة الإرسال خلال ${remaining} ث`; 
        }
      }, 1000);
    });
  </script>
</body>
</html>