<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/razorpay.php';

$ev        = cfg('event');
$fee       = (int) $ev['fee'];
$seatsLeft = seats_left();
$open      = registration_open();
$soldOut   = $seatsLeft !== null && $seatsLeft <= 0;
$waLink    = 'https://wa.me/' . $ev['whatsapp'] . '?text=' . rawurlencode('Hi, I want to know more about Master Mind – ' . $ev['title']);
$csrf      = csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($ev['series']) ?> · <?= e($ev['title']) ?> — <?= e($ev['speaker']) ?></title>
<meta name="description" content="<?= e($ev['subtitle']) ?> with <?= e($ev['speaker']) ?>, <?= e($ev['speaker_role']) ?>. <?= e($ev['date_label']) ?>, <?= e($ev['time_label']) ?> at <?= e($ev['venue']) ?>. Fee ₹<?= $fee ?>.">
<meta name="theme-color" content="#0f0f10">
<meta property="og:title" content="<?= e($ev['series']) ?> · <?= e($ev['tagline']) ?> — <?= e($ev['title']) ?>">
<meta property="og:description" content="Branding Masterclass with <?= e($ev['speaker']) ?> · <?= e($ev['date_label']) ?> · ₹<?= $fee ?>">
<meta property="og:image" content="assets/img/poster.jpg">
<link rel="icon" href="assets/img/favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter+Tight:wght@400;500;600;700;800&family=Instrument+Serif:ital@1&family=Silkscreen:wght@400;700&family=Noto+Sans+Malayalam:wght@700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css?v=1">
</head>
<body>

<!-- ============ Header ============ -->
<header class="nav" id="top">
  <div class="wrap nav__in">
    <a href="#top" class="nav__brand" aria-label="Master Mind – Meet the Expert">
      <img src="assets/img/mastermind-logo.png" alt="Master Mind – Meet the Expert" width="132" height="50">
    </a>
    <nav class="nav__links" aria-label="Sections">
      <a href="#learn">Workshop</a>
      <a href="#speaker">Speaker</a>
      <a href="#details">Details</a>
      <a href="#faq">FAQ</a>
    </nav>
    <a href="#register" class="btn btn--sm btn--pink">Book · ₹<?= $fee ?></a>
  </div>
</header>

<main>
<!-- ============ Hero ============ -->
<section class="hero">
  <div class="hero__frame hero__frame--a" aria-hidden="true"></div>
  <div class="hero__frame hero__frame--b" aria-hidden="true"></div>
  <div class="hero__frame hero__frame--c" aria-hidden="true"></div>

  <div class="wrap hero__grid">
    <div class="hero__copy">
      <p class="eyebrow"><span class="dot"></span><?= e($ev['series']) ?> <i>·</i> <?= e($ev['tagline']) ?> <i>·</i> One Day Workshop</p>

      <h1 class="hero__title">
        <span class="hl hl--blue" data-reveal-hl><span>Explore</span></span>
        <span class="hl hl--pink" data-reveal-hl><span>Branding</span></span>
        <svg class="hero__cursor" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 2l15 11.2-6.6.9 3.9 7.6-3 1.5-3.9-7.7L4 20z" fill="#fff" stroke="#111" stroke-width="1.4" stroke-linejoin="round"/></svg>
      </h1>
      <p class="hero__with">with <strong><?= e($ev['speaker']) ?></strong></p>
      <p class="hero__ml" lang="ml">ബ്രാൻഡുകളുടെ പിന്നിലെ രഹസ്യം അറിയാം!</p>

      <ul class="hero__meta">
        <li><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg><?= e($ev['date_label']) ?></li>
        <li><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg><?= e($ev['time_label']) ?></li>
        <li><svg viewBox="0 0 24 24"><path d="M12 22s7-6.2 7-12a7 7 0 1 0-14 0c0 5.8 7 12 7 12z"/><circle cx="12" cy="10" r="2.5"/></svg><?= e($ev['venue']) ?></li>
      </ul>

      <div class="hero__cta">
        <a href="#register" class="btn btn--pink btn--lg">Book your seat <span class="btn__price">₹<?= $fee ?></span></a>
        <a href="<?= e($waLink) ?>" class="btn btn--ghost" target="_blank" rel="noopener">Ask on WhatsApp</a>
      </div>
    </div>

    <div class="hero__visual">
      <div class="hero__glow" aria-hidden="true"></div>
      <img class="hero__photo" src="assets/img/favour-francis.jpg" alt="<?= e($ev['speaker']) ?>" width="1000" height="1333" fetchpriority="high">
      <div class="hero__badge">
        <span class="hero__badge-name"><?= e($ev['speaker']) ?></span>
        <span class="hero__badge-role"><?= e($ev['speaker_role']) ?></span>
      </div>
    </div>
  </div>

  <!-- Pixel title: particles assemble into the words -->
  <div class="wrap pixel">
    <canvas id="pixelTitle" class="pixel__canvas" aria-hidden="true"></canvas>
    <span class="pixel__your" aria-hidden="true">Your</span>
    <h2 class="sr-only">Favour Your Brand</h2>
  </div>

  <!-- Countdown -->
  <div class="wrap">
    <div class="countdown" id="countdown" data-target="<?= e($ev['starts_at']) ?>">
      <div class="countdown__label">
        <span class="countdown__kicker">Workshop starts in</span>
        <span class="countdown__date"><?= e($ev['date_label']) ?> · <?= e($ev['time_label']) ?></span>
      </div>
      <div class="countdown__units" role="timer" aria-live="off">
        <div class="cd"><span class="cd__n" data-unit="d">00</span><span class="cd__l">Days</span></div>
        <div class="cd"><span class="cd__n" data-unit="h">00</span><span class="cd__l">Hours</span></div>
        <div class="cd"><span class="cd__n" data-unit="m">00</span><span class="cd__l">Minutes</span></div>
        <div class="cd"><span class="cd__n" data-unit="s">00</span><span class="cd__l">Seconds</span></div>
      </div>
      <?php if ($seatsLeft !== null): ?>
        <div class="countdown__seats"><strong><?= $seatsLeft ?></strong> of <?= (int) $ev['seats'] ?> seats left</div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ============ Marquee ============ -->
<div class="marquee" aria-hidden="true">
  <div class="marquee__track">
    <?php for ($i = 0; $i < 2; $i++): ?>
    <span>Master Mind</span><b>✦</b><em>Meet the Expert</em><b>✦</b><span>Favour Your Brand</span><b>✦</b><em>Branding Masterclass</em><b>✦</b><span>26 Nov 2026</span><b>✦</b><em>₹<?= $fee ?> only</em><b>✦</b>
    <?php endfor; ?>
  </div>
</div>

<!-- ============ What you'll learn ============ -->
<section class="section" id="learn">
  <div class="wrap">
    <div class="section__head reveal">
      <p class="kicker">The workshop</p>
      <h2 class="section__title">One day to see brands <span class="serif">the way experts do.</span></h2>
      <p class="section__lead">A hands-on branding masterclass for students, designers, marketers and business owners — what makes people choose one brand over another, and how to build one that earns that choice.</p>
    </div>

    <div class="cards">
      <article class="card reveal"><span class="card__n">01</span><h3>What a brand really is</h3><p>Beyond the logo — positioning, promise and the feeling people carry away.</p></article>
      <article class="card reveal"><span class="card__n">02</span><h3>Secrets behind big brands</h3><p>Real campaigns taken apart to see the decisions that made them work.</p></article>
      <article class="card reveal"><span class="card__n">03</span><h3>Identity &amp; visual language</h3><p>Name, logo, colour, type and tone working together as one system.</p></article>
      <article class="card reveal"><span class="card__n">04</span><h3>Brand storytelling</h3><p>Turning a product into a story people remember and repeat.</p></article>
      <article class="card reveal"><span class="card__n">05</span><h3>Building your own brand</h3><p>A practical framework you can apply to a business, a client or yourself.</p></article>
      <article class="card reveal"><span class="card__n">06</span><h3>Q&amp;A with the expert</h3><p>Bring your brand questions — get direct answers from <?= e($ev['speaker']) ?>.</p></article>
    </div>
  </div>
</section>

<!-- ============ Speaker ============ -->
<section class="section section--alt" id="speaker">
  <div class="wrap speaker">
    <div class="speaker__photo reveal">
      <img src="assets/img/favour-francis.jpg" alt="<?= e($ev['speaker']) ?>" loading="lazy" width="1000" height="1333">
      <span class="speaker__tag">Meet the Expert</span>
    </div>
    <div class="speaker__copy reveal">
      <p class="kicker">Your mentor</p>
      <h2 class="speaker__name"><?= e($ev['speaker']) ?></h2>
      <p class="speaker__role"><?= e($ev['speaker_role']) ?></p>
      <p class="section__lead">Favour Francis is a creative director and brand consultant who has spent years shaping how brands look, speak and connect with people. In this edition of Master Mind he opens up his process — the thinking, the craft and the stories behind the brands we all know.</p>
      <div class="speaker__series">
        <img src="assets/img/mastermind-logo.png" alt="Master Mind" loading="lazy" width="132" height="50">
        <p><strong>Master Mind</strong> is a series by MAC Media School where industry experts spend a day with learners — sharing real experience, not textbook theory.</p>
      </div>
    </div>
  </div>
</section>

<!-- ============ Details strip ============ -->
<section class="section" id="details">
  <div class="wrap">
    <div class="details reveal">
      <div class="details__item"><span class="details__k">Date</span><span class="details__v">26 Nov</span><span class="details__s">Thursday, 2026</span></div>
      <div class="details__item"><span class="details__k">Time</span><span class="details__v"><?= e($ev['time_label']) ?></span><span class="details__s">Full-day workshop</span></div>
      <div class="details__item"><span class="details__k">Venue</span><span class="details__v details__v--sm"><?= e($ev['venue']) ?></span><a class="details__s link" href="<?= e($ev['venue_map']) ?>" target="_blank" rel="noopener">Open in Maps →</a></div>
      <div class="details__item details__item--fee"><span class="details__k">Fee</span><span class="details__v">₹<?= $fee ?></span><span class="details__s">Per participant</span></div>
    </div>
  </div>
</section>

<!-- ============ Register ============ -->
<section class="section section--register" id="register">
  <div class="wrap reg">
    <div class="reg__info reveal">
      <p class="kicker">Booking</p>
      <h2 class="section__title">Reserve your seat <span class="serif">for ₹<?= $fee ?>.</span></h2>
      <p class="section__lead">Fill in your details and complete the payment. Your registration ID confirms your seat — keep it handy on the day.</p>
      <ul class="reg__points">
        <li>Seats are limited and confirmed on payment</li>
        <li>Registration ID shown instantly after booking</li>
        <li>Questions? Call <a class="link" href="tel:<?= e(preg_replace('/\s+/', '', $ev['phone'])) ?>"><?= e($ev['phone']) ?></a></li>
      </ul>
      <?php if ($seatsLeft !== null && !$soldOut): ?>
        <div class="seatbar" style="--fill: <?= round(100 * (1 - $seatsLeft / max(1, (int) $ev['seats']))) ?>%">
          <div class="seatbar__track"><span></span></div>
          <p><strong><?= $seatsLeft ?></strong> seats left</p>
        </div>
      <?php endif; ?>
    </div>

    <div class="reg__card reveal">
      <?php if (!$open): ?>
        <div class="reg__closed"><h3>Registrations closed</h3><p>This edition has started. Follow MAC Media School for the next Master Mind.</p></div>
      <?php elseif ($soldOut): ?>
        <div class="reg__closed"><h3>Houseful!</h3><p>All seats are booked. Message us on <a class="link" href="<?= e($waLink) ?>" target="_blank" rel="noopener">WhatsApp</a> to join the waitlist.</p></div>
      <?php else: ?>
      <form id="regForm" class="form" novalidate>
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <div class="hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

        <div class="field">
          <label for="f-name">Full name</label>
          <input id="f-name" name="name" type="text" autocomplete="name" required maxlength="120">
          <small class="field__err"></small>
        </div>
        <div class="row">
          <div class="field">
            <label for="f-phone">Mobile (WhatsApp)</label>
            <div class="phone"><span>+91</span><input id="f-phone" name="phone" type="tel" inputmode="numeric" autocomplete="tel-national" required maxlength="14" placeholder="98XXXXXXXX"></div>
            <small class="field__err"></small>
          </div>
          <div class="field">
            <label for="f-email">Email</label>
            <input id="f-email" name="email" type="email" autocomplete="email" required maxlength="160">
            <small class="field__err"></small>
          </div>
        </div>
        <div class="row">
          <div class="field">
            <label for="f-city">City / Place</label>
            <input id="f-city" name="city" type="text" autocomplete="address-level2" required maxlength="80">
            <small class="field__err"></small>
          </div>
          <div class="field">
            <label for="f-prof">You are a</label>
            <select id="f-prof" name="profession" required>
              <option value="">Choose one</option>
              <option>Student</option><option>Designer</option><option>Marketer</option>
              <option>Entrepreneur</option><option>Business Owner</option>
              <option>Working Professional</option><option>Other</option>
            </select>
            <small class="field__err"></small>
          </div>
        </div>
        <div class="field">
          <label for="f-org">College / Company <span class="opt">optional</span></label>
          <input id="f-org" name="organization" type="text" maxlength="160">
          <small class="field__err"></small>
        </div>
        <div class="field">
          <label for="f-exp">What do you want to learn? <span class="opt">optional</span></label>
          <textarea id="f-exp" name="expectation" rows="3" maxlength="1000"></textarea>
          <small class="field__err"></small>
        </div>

        <div class="form__foot">
          <div class="form__total"><span>Total</span><strong>₹<?= $fee ?></strong></div>
          <button type="submit" class="btn btn--pink btn--lg btn--block" id="regBtn">
            <span class="btn__label"><?= razorpay_enabled() ? 'Register &amp; Pay ₹' . $fee : 'Register Now' ?></span>
            <span class="btn__spin" aria-hidden="true"></span>
          </button>
          <p class="form__msg" role="alert"></p>
          <p class="form__note">By registering you agree to be contacted about this workshop on WhatsApp / phone.</p>
        </div>
      </form>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ============ FAQ ============ -->
<section class="section" id="faq">
  <div class="wrap faq">
    <div class="section__head reveal">
      <p class="kicker">FAQ</p>
      <h2 class="section__title">Good to know</h2>
    </div>
    <div class="faq__list reveal">
      <details><summary>Who can attend?</summary><p>Anyone curious about branding — students, designers, marketers, entrepreneurs and business owners. No prior experience needed.</p></details>
      <details><summary>How do I pay the ₹<?= $fee ?> fee?</summary><p><?= razorpay_enabled() ? 'Pay securely online with UPI, card or net banking right after filling the form.' : 'After filling the form you will see our UPI QR code. Scan it with GPay, PhonePe, Paytm or any UPI app, pay ₹' . $fee . ' and enter the UPI transaction (UTR) number. We confirm your seat on WhatsApp.' ?></p></details>
      <details><summary>How do I know my seat is confirmed?</summary><p>You get a registration ID (like MM26-XXXXXX) as soon as you register. Once the payment is confirmed your seat is reserved. Show the ID at the venue.</p></details>
      <details><summary>Where is the venue?</summary><p><?= e($ev['venue']) ?>. <a class="link" href="<?= e($ev['venue_map']) ?>" target="_blank" rel="noopener">Open in Google Maps</a>.</p></details>
      <details><summary>Is the fee refundable?</summary><p>Please contact us on <?= e($ev['phone']) ?> if you can’t make it — we’ll do our best to help.</p></details>
    </div>
  </div>
</section>
</main>

<!-- ============ Footer ============ -->
<footer class="footer">
  <div class="wrap footer__in">
    <div class="footer__brand">
      <img src="assets/img/mastermind-logo.png" alt="Master Mind" width="132" height="50" loading="lazy">
      <span class="footer__by">a series by</span>
      <a href="<?= e($ev['website']) ?>" target="_blank" rel="noopener"><img class="footer__mac" src="assets/img/mac-media-school.png" alt="MAC Media School" width="118" height="51" loading="lazy"></a>
    </div>
    <div class="footer__contact">
      <a href="tel:<?= e(preg_replace('/\s+/', '', $ev['phone'])) ?>"><?= e($ev['phone']) ?></a>
      <a href="<?= e($waLink) ?>" target="_blank" rel="noopener">WhatsApp</a>
      <a href="<?= e($ev['website']) ?>" target="_blank" rel="noopener">school.macadz.com</a>
    </div>
    <p class="footer__copy">© <?= date('Y') ?> MAC Media School. All rights reserved.</p>
  </div>
</footer>

<!-- Mobile sticky CTA -->
<a href="#register" class="sticky-cta">Book your seat · ₹<?= $fee ?></a>

<!-- Result modal -->
<div class="modal" id="modal" hidden>
  <div class="modal__box" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <button class="modal__x" type="button" data-close aria-label="Close">×</button>
    <div class="modal__body" id="modalBody"></div>
  </div>
</div>

<script>
window.MM = {
  csrf: <?= json_encode($csrf) ?>,
  fee: <?= $fee ?>,
  whatsapp: <?= json_encode($ev['whatsapp']) ?>,
  phone: <?= json_encode($ev['phone']) ?>,
  dateLabel: <?= json_encode($ev['date_label'] . ' · ' . $ev['time_label']) ?>
};
</script>
<script src="assets/js/vendor/qrcode.js" defer></script>
<?php if (razorpay_enabled()): ?>
<script src="https://checkout.razorpay.com/v1/checkout.js" defer></script>
<?php endif; ?>
<script src="assets/js/main.js?v=1" defer></script>
</body>
</html>
