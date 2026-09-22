<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$products = $pdo->query('SELECT * FROM products WHERE is_active = 1 ORDER BY type, id')->fetchAll();
$groups = ['vehicle' => [], 'trek' => [], 'intl' => []];
foreach ($products as $product) {
    $groups[$product['type']][] = $product;
}
$user = current_user();

function render_product_card(array $product): void
{
    $type = (string) $product['type'];
    $isVehicle = $type === 'vehicle';
    $isTrek = $type === 'trek';
    $label = $isVehicle ? 'per day' : 'per person';
    ?>
    <article class="service-card" data-price="<?= e($product['base_price']) ?>">
        <div class="service-image" style="background-image:url('<?= e($product['image']) ?>')">
            <span><?= e($isVehicle ? 'Vehicle rental' : ($isTrek ? 'Nepal trek' : 'International tour')) ?></span>
        </div>
        <div class="service-content">
            <h3><?= e($product['title']) ?></h3>
            <p><?= e($product['description']) ?></p>
            <?php if (!empty($product['itinerary'])): ?>

<button
    class="btn btn-outline itinerary-btn"
    data-title="<?= e($product['title']) ?>"
    data-itinerary="<?= e($product['itinerary']) ?>"
    type="button">
    View Itinerary
</button>

<?php endif; ?>
            <div class="service-options">
                <?php if ($isVehicle): ?>
                    <label>Days
                        <input class="option-days" type="number" min="1" max="30" value="1">
                    </label>
                <?php endif; ?>
                <label>Travelers
                    <input class="option-travelers" type="number" min="1" max="<?= (int) $product['max_pax'] ?>" value="1">
                </label>
            </div>
            <?php if ($isTrek): ?>
                <p class="discount-hint">Groups of 5 or more receive NPR 4,000 off per trekker.</p>
            <?php elseif ($type === 'intl'): ?>
                <p class="discount-hint">Groups of 4 or more receive a 5% discount.</p>
            <?php endif; ?>
        </div>
        <div class="service-footer">
            <div><small><?= e($label) ?></small><strong>NPR <?= e(number_format((float) $product['base_price'])) ?></strong></div>
            <button
                class="btn btn-primary book-button"
                type="button"
                data-product-id="<?= e($product['id']) ?>"
                data-product-type="<?= e($type) ?>"
                data-title="<?= e($product['title']) ?>"
                data-price="<?= e($product['base_price']) ?>"
                data-max-pax="<?= (int) $product['max_pax'] ?>"
            >Book now</button>
        </div>
    </article>
    <?php
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="app-url" content="<?= e(app_url()) ?>">
    <title>Gantavya Travel & Tours</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<header class="site-header">
    <a class="brand" href="#top">Gantavya<span>.</span></a>
    <button class="mobile-nav-toggle" type="button" aria-label="Toggle navigation">Menu</button>
    <nav class="main-nav">
        <a href="#vehicles">Vehicles</a>
        <a href="#treks">Treks</a>
        <a href="#international">International</a>
        <a href="#contact">Contact</a>
        <?php if ($user): ?>
            <a href="my-bookings.php">My Bookings</a>
            <?php if ($user['role'] === 'admin'): ?><a href="admin/index.php">Admin</a><?php endif; ?>
            <span class="nav-user">Hi, <?= e(explode(' ', $user['full_name'])[0]) ?></span>
            <form action="api/logout.php" method="post" class="inline-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button class="nav-link-button" type="submit">Logout</button>
            </form>
        <?php else: ?>
            <button class="btn btn-outline open-auth" type="button">Login / Register</button>
        <?php endif; ?>
    </nav>
</header>

<main id="top">
    <section class="hero">
        <div class="hero-overlay"></div>
        <div class="hero-content">
            <p class="eyebrow light">Travel across Nepal and beyond</p>
            <h1>Your complete journey,<br>managed in one place.</h1>
            <p>Book vehicles, guided treks, and international tours. </p>
            <div class="button-row">
                <a class="btn btn-primary" href="#services">Explore services</a>
                <a class="btn btn-ghost" href="my-bookings.php">Track a booking</a>
            </div>
        </div>
    </section>

    <section class="intro" id="services">
        <p class="eyebrow">Choose a service</p>
        <h2>Plan the trip that fits you</h2>
        <p>Choose your preferred service, select your group size, and complete the travel details when you are ready to book.</p>
    </section>

    <section class="service-section" id="vehicles">
        <div class="section-heading">
            <div><p class="eyebrow">Flexible transport</p><h2>Vehicle rentals</h2></div>
            
        </div>
        <div class="service-grid">
            <?php foreach ($groups['vehicle'] as $product) render_product_card($product); ?>
        </div>
    </section>

    <section class="service-section alt" id="treks">
        <div class="section-heading">
            <div><p class="eyebrow">Guided adventures</p><h2>Nepal trekking</h2></div>
    
        </div>
        <div class="service-grid">
            <?php foreach ($groups['trek'] as $product) render_product_card($product); ?>
        </div>
    </section>

    <section class="service-section" id="international">
        <div class="section-heading">
            <div><p class="eyebrow">Travel farther</p><h2>International tours</h2></div>
            
        </div>
        <div class="service-grid">
            <?php foreach ($groups['intl'] as $product) render_product_card($product); ?>
        </div>
    </section>

    <section class="contact-section" id="contact">
        <div>
            <p class="eyebrow">Need assistance?</p>
            <h2>Talk with Gantavya</h2>
            <p>For custom routes, paid-booking changes, or group travel support, contact our team.</p>
        </div>
        <div class="contact-actions">
            <a href="tel:9745384731">9745384731</a>
            <a href="mailto:info@gantavya.com">info@gantavya.com</a>
        </div>
    </section>
</main>

<footer class="site-footer">
    <a class="brand inverse" href="#top">Gantavya<span>.</span></a>
    <p>Travel & Tours Management Information System</p>
    <p>&copy; <?= date('Y') ?> Gantavya Travel & Tours Pvt. Ltd.</p>
</footer>

<button class="chat-toggle" id="chat-toggle" type="button" aria-label="Open travel assistant" aria-expanded="false">
    <span class="chat-toggle-icon" aria-hidden="true">✦</span>
    <span class="chat-toggle-label">Need help?</span>
</button>

<section class="chat-panel" id="chat-panel" aria-hidden="true" aria-label="Gantavya travel assistant">
    <header class="chat-panel-header">
        <div class="chat-avatar" aria-hidden="true">G</div>
        <div>
            <strong>Gantavya Assistant</strong>
            <span><i></i> Ready to help</span>
        </div>
        <button class="chat-close" id="chat-close" type="button" aria-label="Close travel assistant">×</button>
    </header>
    <div class="chat-messages" id="chat-messages" aria-live="polite">
        <div class="chat-message assistant">Namaste! I can help you find vehicles, trekking packages, international tours, booking details, and payment support. What would you like to know?</div>
    </div>
    <div class="chat-suggestions" id="chat-suggestions">
        <button type="button" data-chat-message="Show me vehicle rentals">Vehicle rentals</button>
        <button type="button" data-chat-message="Which trekking packages are available?">Trekking packages</button>
        <button type="button" data-chat-message="How do I track my booking?">Track booking</button>
    </div>
    <form class="chat-form" id="chat-form">
        <label class="sr-only" for="chat-input">Ask the travel assistant</label>
        <input id="chat-input" name="message" type="text" maxlength="500" autocomplete="off" placeholder="Ask about a trip or booking..." required>
        <button type="submit" aria-label="Send message">➤</button>
    </form>
    <a class="chat-human-link" href="tel:9745384731">Prefer a person? Call 9745384731</a>
</section>

<div class="modal" id="auth-modal" aria-hidden="true">
    <div class="modal-panel narrow" role="dialog" aria-modal="true" aria-labelledby="auth-title">
        <button class="modal-close" type="button" data-close-modal aria-label="Close">×</button>
        <div class="tab-row">
            <button class="tab active" type="button" data-auth-tab="login">Login</button>
            <button class="tab" type="button" data-auth-tab="register">Register</button>
        </div>

        <form id="login-form" class="auth-form active">
            <p class="eyebrow">Welcome back</p>
            <h2 id="auth-title">Login to Gantavya</h2>
            <label>Email address<input type="email" name="email" autocomplete="email" required></label>
            <label>Password<input type="password" name="password" autocomplete="current-password" required></label>
            <button class="btn btn-primary full" type="submit">Login</button>
            <p class="form-message" aria-live="polite"></p>
        </form>

        <form id="register-form" class="auth-form">
            <p class="eyebrow">Welcome to Gantavya!</p>
            <h2>Create your account</h2>
            <label>Full name<input type="text" name="full_name" maxlength="100" autocomplete="name" required></label>
            <label>Email address<input type="email" name="email" autocomplete="email" required></label>
            <label>Phone number <small>(optional)</small><input type="tel" name="phone" maxlength="20" autocomplete="tel" placeholder="98XXXXXXXX"></label>
            <label>Password<input type="password" name="password" minlength="8" autocomplete="new-password" required></label>
            <label>Confirm password<input type="password" name="password_confirmation" minlength="8" autocomplete="new-password" required></label>
            <button class="btn btn-primary full" type="submit">Create account</button>
            <p class="form-message" aria-live="polite"></p>
        </form>
    </div>
</div>
<div class="modal" id="itinerary-modal" aria-hidden="true">

<div class="modal-panel">

<button class="modal-close" id="close-itinerary">×</button>

<h2 id="itinerary-title"></h2>

<hr>

<div id="itinerary-content"></div>

</div>

</div>
<div class="modal" id="checkout-modal" aria-hidden="true">
    <div class="modal-panel checkout-panel" role="dialog" aria-modal="true" aria-labelledby="checkout-title">
        <button class="modal-close" type="button" data-close-modal aria-label="Close">×</button>
        <div class="checkout-heading">
            <div><p class="eyebrow">Booking checkout</p><h2 id="checkout-title">Complete your travel details</h2></div>
            
        </div>

        <form id="booking-form">
            <input type="hidden" name="product_id" id="checkout-product-id">
            <input type="hidden" name="travelers" id="checkout-travelers">
            <input type="hidden" name="days" id="checkout-days">

            <section class="checkout-summary">
                <div><small>Selected service</small><strong id="summary-title">-</strong></div>
                <div><small>Scope</small><strong id="summary-scope">-</strong></div>
                <div><small>Estimated total</small><strong id="summary-total">-</strong></div>
            </section>

            <div class="form-grid">
                <label>Full name<input type="text" name="customer_name" maxlength="100" value="<?= e($user['full_name'] ?? '') ?>" autocomplete="name" required></label>
                <label>Email address<input type="email" name="customer_email" value="<?= e($user['email'] ?? '') ?>" autocomplete="email" required></label>
                <label>Phone number<input type="tel" name="customer_phone" maxlength="20" value="<?= e($user['phone'] ?? '') ?>" placeholder="98XXXXXXXX" autocomplete="tel" required></label>
                <label>Travel / pickup date<input type="date" name="service_date" min="<?= e(date('Y-m-d')) ?>" max="<?= e(date('Y-m-d', strtotime('+2 years'))) ?>" required></label>
                <label class="span-2">Pickup or meeting address<input type="text" name="pickup_address" maxlength="255" placeholder="Area, landmark, city" autocomplete="street-address" required></label>
                <label class="span-2">Destination or drop-off point<input type="text" name="destination_address" maxlength="255" placeholder="Destination, hotel, trailhead, or airport" required></label>
                <label class="span-2">Special requests <small>(optional)</small><textarea name="special_requests" maxlength="2000" rows="3" placeholder="Luggage, accessibility, room, food, or timing notes"></textarea></label>
            </div>

            
            <button class="btn btn-khalti full" id="booking-submit" type="submit">Continue</button>
            <p class="form-message" aria-live="polite"></p>
        </form>
    </div>
</div>

<script>
window.GANTAVYA = {
    loggedIn: <?= $user ? 'true' : 'false' ?>,
    csrfToken: <?= json_encode(csrf_token()) ?>,
    appUrl: <?= json_encode(app_url()) ?>
};
</script>
<script src="assets/js/app.js"></script>
</body>
</html>
