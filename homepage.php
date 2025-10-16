<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Graduation Internship System</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
<style>
/* ===== Reset & Global ===== */
* {margin:0; padding:0; box-sizing:border-box;}
body {font-family:'Inter',sans-serif; line-height:1.7; background:#f6f8fb; color:#0f172a; overflow-x:hidden; scroll-behavior:smooth;}
a, button, input, textarea {font-family:'Inter',sans-serif;}
a {text-decoration:none;}
img {max-width:100%; display:block;}

/* ===== Navbar ===== */
nav {position:fixed; top:0; left:0; right:0; background:#ffffff; border-bottom:1px solid #e5e7eb; padding:0.9rem 5vw; display:flex; justify-content:space-between; align-items:center; z-index:1000; transition:all 0.25s ease; box-shadow:0 2px 10px rgba(15,23,42,0.04);} 
nav h1 {font-size:clamp(1.4rem,2.2vw,1.7rem); font-weight:800; color:#0b1f3a; letter-spacing:0.5px;}
.nav-links {display:flex; gap:1.25rem; flex-wrap:wrap; align-items:center;}
.nav-links button {background:none; border:none; cursor:pointer; font-size:clamp(0.95rem,1.1vw,1rem); font-weight:600; color:#334155; padding:0.6rem 0.2rem; position:relative; transition:color 0.2s ease;}
.nav-links button:hover {color:#0ea5a8;}
.nav-links button::after {content:''; position:absolute; left:0; bottom:-6px; width:0%; height:2px; background:#0ea5a8; transition:width 0.25s ease;}
.nav-links button:hover::after {width:100%;}
.auth-buttons {display:flex; gap:0.8rem;}
.signup-btn, .login-btn {padding:0.55rem 1.2rem; font-weight:700; font-size:0.95rem; border-radius:12px; cursor:pointer; transition:all 0.25s ease;}
.signup-btn {border:1px solid #0ea5a8; background:#0ea5a8; color:white;}
.signup-btn:hover {filter:brightness(0.95); box-shadow:0 8px 20px rgba(14,165,168,0.25); transform:translateY(-1px);}
.login-btn {border:1px solid #cbd5e1; background:#ffffff; color:#0b1f3a;}
.login-btn:hover {border-color:#0ea5a8; color:#0ea5a8; transform:translateY(-1px);} 
.hamburger {display:none; flex-direction:column; cursor:pointer; gap:5px;}
.hamburger span {width:28px; height:3px; background:#0b1f3a; border-radius:3px; transition:all 0.3s;}

/* ===== Hero Section (Real Photo) ===== */
.hero {display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; padding:12vh 5vw; min-height:92vh; position:relative; color:#ffffff; background: #0b1f3a;} 
.hero::before {content:''; position:absolute; inset:0; background:url('https://images.unsplash.com/photo-1551836022-d5d88e9218df?q=80&w=1600&auto=format&fit=crop') center/cover no-repeat; filter:brightness(0.55);}
.hero::after {content:''; position:absolute; inset:0; background:linear-gradient(90deg, rgba(11,31,58,0.85) 0%, rgba(14,165,168,0.35) 100%);} 
.hero-content {flex:1 1 520px; padding:2rem; z-index:1; text-align:left;}
.hero-content h2 {font-size:clamp(2.2rem,5vw,3.8rem); font-weight:900; letter-spacing:0.2px; margin-bottom:1rem;}
.hero-content p {font-size:clamp(1rem,1.6vw,1.2rem); color:#e2e8f0; margin-bottom:2rem; max-width:640px;}
.hero-content button {padding:14px 32px; background:#0ea5a8; color:#ffffff; border:none; border-radius:12px; font-weight:700; font-size:1rem; cursor:pointer; transition:all 0.25s ease; box-shadow:0 10px 24px rgba(14,165,168,0.25);} 
.hero-content button:hover {filter:brightness(0.95); transform:translateY(-2px);} 
.hero-image {flex:1 1 400px; display:none;}
.hero-image img {display:none;}

/* ===== Features Section ===== */
.features-section {background:#ffffff; padding:90px 5vw; text-align:center;}
.features-section h2 {font-size:2.4rem; color:#0b1f3a; margin-bottom:10px; font-weight:800;}
.features-section h2::after {content:''; display:block; height:3px; width:64px; background:#0ea5a8; margin:12px auto 0 auto; border-radius:2px;}
.features-container {display:flex; justify-content:center; gap:28px; flex-wrap:wrap; margin-top:44px;}
.feature-card {background:#ffffff; padding:28px 22px; border-radius:16px; border:1px solid #e5e7eb; width:300px; transition:transform 0.25s ease, box-shadow 0.25s ease, opacity 0.6s; text-align:center; opacity:0; transform:translateY(22px);}
.feature-card.visible {opacity:1; transform:translateY(0);} 
.feature-card img {width:72px; height:72px; object-fit:cover; border-radius:12px; margin:0 auto 18px auto;}
.feature-card h3 {color:#0b1f3a; font-size:1.15rem; margin-bottom:8px; font-weight:700;}
.feature-card p {font-size:0.98rem; color:#475569; line-height:1.7;}
.feature-card:hover {transform:translateY(-6px); box-shadow:0 14px 38px rgba(2,6,23,0.08);} 

/* ===== Contact Section ===== */
.contact-section {background:#f6f8fb; padding:6rem 5vw; color:#0f172a;}
.contact-container {display:flex; justify-content:space-between; align-items:flex-start; max-width:1200px; margin:0 auto; gap:2rem; flex-wrap:wrap;}
.contact-info, .contact-form {flex:1 1 420px; background:#ffffff; padding:2rem; border-radius:16px; border:1px solid #e5e7eb; box-shadow:0 8px 30px rgba(2,6,23,0.06); transition:transform 0.2s ease, box-shadow 0.2s ease;}
.contact-info:hover, .contact-form:hover {transform:translateY(-3px); box-shadow:0 12px 36px rgba(2,6,23,0.08);} 
.contact-info h2 {font-size:2rem; margin-bottom:1rem; color:#0b1f3a;}
.contact-info p {font-size:1rem; margin:0.6rem 0; color:#334155;}
.contact-info a {color:#0ea5a8; text-decoration:none;}
.contact-info a:hover {text-decoration:underline;}
.contact-form form {display:flex; flex-direction:column; gap:1rem;}
.contact-form input, .contact-form textarea {width:100%; padding:0.9rem 1rem; border-radius:12px; border:1px solid #e5e7eb; background:#ffffff; color:#0f172a; font-size:1rem; transition:border-color 0.2s ease, box-shadow 0.2s ease; resize:none;}
.contact-form input:focus, .contact-form textarea:focus {outline:none; border-color:#0ea5a8; box-shadow:0 0 0 4px rgba(14,165,168,0.15);} 
.contact-form button {padding:0.9rem 1.6rem; border:none; border-radius:12px; background:#0ea5a8; color:#ffffff; font-weight:700; font-size:1rem; cursor:pointer; align-self:flex-start; transition:all 0.25s ease;}
.contact-form button:hover {filter:brightness(0.95); box-shadow:0 10px 24px rgba(14,165,168,0.25);} 

/* ===== Reviews Section ===== */
.review-feedback-container {margin:0 auto; padding:5rem 5vw; color:#0f172a; background:#ffffff;}
.review-feedback-container h1 {font-size:2.2rem; text-align:center; margin-bottom:2rem; color:#0b1f3a; font-weight:800;}
.review-form {background:#ffffff; padding:2rem; border-radius:16px; border:1px solid #e5e7eb; box-shadow:0 8px 30px rgba(2,6,23,0.06); margin-bottom:2rem; display:flex; flex-direction:column; gap:1.1rem;}
.review-form input, .review-form textarea {width:100%; padding:0.9rem 1rem; border-radius:12px; border:1px solid #e5e7eb; background:#ffffff; color:#0f172a; font-size:1rem;}
.review-form input:focus, .review-form textarea:focus {outline:none; border-color:#0ea5a8; box-shadow:0 0 0 4px rgba(14,165,168,0.15);} 
.star-rating-container {display:flex; align-items:center; gap:0.8rem; margin-top:0.3rem;}
.star-rating {display:flex; flex-direction:row-reverse; justify-content:flex-start; gap:0.2rem;}
.star-rating input {display:none;}
.star-rating label {font-size:1.8rem; color:#cbd5e1; cursor:pointer; transition:color 0.2s ease, transform 0.2s ease;}
.star-rating label:hover, .star-rating input:checked ~ label, .star-rating label:hover ~ label {color:#f59e0b; transform:scale(1.15);} 
.submit-btn {padding:0.9rem 1.6rem; border-radius:12px; border:none; background:#0ea5a8; color:#fff; font-weight:700; font-size:1rem; cursor:pointer; align-self:flex-start;}
.submit-btn:hover {filter:brightness(0.95); box-shadow:0 10px 24px rgba(14,165,168,0.25);} 
.reviews-section {margin-top:2rem; text-align:center;}
.reviews-section h2 {font-size:1.6rem; margin-bottom:1rem; color:#0b1f3a;}
.review-item {background:#ffffff; padding:1.2rem 1.5rem; border-radius:16px; margin-bottom:1rem; border:1px solid #e5e7eb; box-shadow:0 6px 22px rgba(2,6,23,0.05);} 
.review-item:hover {transform:translateY(-2px); box-shadow:0 10px 28px rgba(2,6,23,0.07);} 
.review-header {display:flex; justify-content:space-between; align-items:center; margin-bottom:0.4rem;}
.review-rating-container {display:flex; align-items:center; gap:0.4rem;}
.review-rating {color:#f59e0b; font-size:1.2rem;}
.rating-label {font-size:0.95rem; color:#0b1f3a;}
.review-date {font-size:0.85rem; color:#64748b;}
.review-feedback {font-size:0.98rem; line-height:1.7; color:#334155; text-align:left; margin-top:0.4rem;}

/* ===== Footer ===== */
footer {background:#0b1f3a; color:#e2e8f0; text-align:center; padding:2rem; font-size:0.95rem; border-top:1px solid rgba(255,255,255,0.06);} 
footer a {color:#0ea5a8;}

/* ===== Animations ===== */
@keyframes float {0%,100%{transform:translateY(0);}50%{transform:translateY(-10px);}}
@keyframes fadeInUp {from{opacity:0; transform:translateY(30px);}to{opacity:1; transform:translateY(0);}}

/* ===== Responsive ===== */
@media (max-width:768px){.nav-links{position:fixed; top:0; right:-100%; height:100vh; width:72%; flex-direction:column; background:#ffffff; padding-top:6rem; gap:1.4rem; transition:right 0.25s; box-shadow:-4px 0 12px rgba(0,0,0,0.1);} .nav-links.active{right:0;} .hamburger{display:flex;} .hero-content{text-align:center;} }
@media (max-width:900px){.hero{flex-direction:column; padding:10vh 5vw;} .hero-content{flex:1 1 100%;} .contact-container, .reviews-section .review-form{flex-direction:column;} }
</style>
</head>
<body>

<nav>
<h1>GIS</h1>
<div class="nav-links">
<button onclick="scrollToSection('hero')">Home</button>
<button onclick="scrollToSection('features')">Features</button>
<button onclick="scrollToSection('contact')">Contact</button>
<button onclick="scrollToSection('reviews')">Reviews</button>
</div>
<div class="auth-buttons">
<button class="signup-btn" onclick="window.location.href='signup.php'">Sign Up</button>
<button class="login-btn" onclick="window.location.href='login.php'">Login</button>

</div>
<div class="hamburger"><span></span><span></span><span></span></div>
</nav>

<section class="hero" id="hero">
<div class="hero-content">
<h2>Find Your Internship</h2>
<h2>in lebanon</h2>
<p>Connect students with companies offering meaningful internship opportunities. Track applications and gain hands-on experience.</p>
<button>Get Started</button>
</div>
<div class="hero-image">
<img src="img/Gemini_Generated_Image_8a5g48a5g48a5g48-removebg-preview (2).png" alt="Internship Illustration">
</div>
</section>

<section class="features-section" id="features">
<h2>Our Features</h2>
<div class="features-container">
<div class="feature-card"><img src="img/register.png" alt="Easy Registration"><h3>Easy Registration</h3><p>Sign up quickly and create your professional profile.</p></div>
<div class="feature-card"><img src="img/2.png" alt="Browse Opportunities"><h3>Browse Opportunities</h3><p>Find internships that match your skills and interests.</p></div>
<div class="feature-card"><img src="img/progress.png" alt="Track Progress"><h3>Track Progress</h3><p>Monitor applications, feedback, and growth.</p></div>
</div>
</section>

<section class="contact-section" id="contact">
<div class="contact-container">
<div class="contact-info">
<h2>Contact Us</h2>
<p><strong>Email:</strong> <a href="mailto:info@internship.com">info@internship.com</a></p>
<p><strong>Phone:</strong> <a href="tel:+961123456">+961 123 456</a></p>
<p><strong>Address:</strong> Beirut, Lebanon</p>
</div>
<div class="contact-form">
<form>
<input type="text" placeholder="Name" required>
<input type="email" placeholder="Email" required>
<textarea placeholder="Message" rows="5" required></textarea>
<button type="submit">Send Message</button>
</form>
</div>
</div>
</section>

<div class="review-feedback-container" id="reviews">
<h1>Student Feedback</h1>
<form class="review-form" onsubmit="return handleReviewSubmit(event)">
<div class="form-group">
<label for="rating">Rating:</label>
<div class="star-rating-container">
<div class="star-rating">
<input type="radio" id="star5" name="rating" value="5" /><label for="star5" title="Excellent">★</label>
<input type="radio" id="star4" name="rating" value="4" /><label for="star4" title="Very Good">★</label>
<input type="radio" id="star3" name="rating" value="3" /><label for="star3" title="Good">★</label>
<input type="radio" id="star2" name="rating" value="2" /><label for="star2" title="Fair">★</label>
<input type="radio" id="star1" name="rating" value="1" /><label for="star1" title="Poor">★</label>
</div>
<div class="rating-text">Select your rating</div>
</div>
</div>
<div class="form-group">
<label for="feedback">Your Feedback:</label>
<textarea id="feedback" rows="5" placeholder="Share your experience with us..." required></textarea>
</div>
<button type="submit" class="submit-btn">Submit Review</button>
</form>

<div class="reviews-section">
<h2>All Reviews</h2>
<p id="no-reviews-msg">No reviews yet. Be the first to share your feedback!</p>
<div id="reviews-container"></div>
</div>
</div>

<footer>
&copy; 2025 Graduation Internship System | <a href="#">Team Project</a>
</footer>

<script>
// Hamburger toggle
const hamburger = document.querySelector('.hamburger');
const navLinks = document.querySelector('.nav-links');
hamburger.addEventListener('click', ()=> navLinks.classList.toggle('active'));

// Smooth scroll function
function scrollToSection(sectionId) {
  const section = document.getElementById(sectionId);
  if(section) section.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// Feature card animation on scroll
const featureCards = document.querySelectorAll('.feature-card');
const observer = new IntersectionObserver(entries=>{
  entries.forEach(entry=>{
    if(entry.isIntersecting) entry.target.classList.add('visible');
  });
},{threshold:0.2});
featureCards.forEach(card=>observer.observe(card));

// Reviews logic
let reviews = JSON.parse(localStorage.getItem("reviews") || "[]");

function handleReviewSubmit(event) {
  event.preventDefault();
  const rating = document.querySelector('input[name="rating"]:checked')?.value;
  const feedback = document.getElementById("feedback").value.trim();
  if(!rating){alert("Please select a rating."); return false;}
  if(!feedback){alert("Please write some feedback."); return false;}
  const review = { rating, feedback, date: new Date().toLocaleString() };
  reviews.unshift(review);
  localStorage.setItem("reviews", JSON.stringify(reviews));
  displayReviews();
  document.getElementById("feedback").value = "";
  document.querySelectorAll('input[name="rating"]').forEach(input => input.checked = false);
  document.querySelector('.rating-text').textContent = 'Select your rating';
}

function displayReviews() {
  const container = document.getElementById("reviews-container");
  const noReviewsMsg = document.getElementById("no-reviews-msg");
  container.innerHTML = "";
  if(reviews.length===0){noReviewsMsg.style.display="block"; return;}
  noReviewsMsg.style.display="none";
  const ratingTexts = {'5':'Excellent','4':'Very Good','3':'Good','2':'Fair','1':'Poor'};
  reviews.forEach(review=>{
    const div = document.createElement("div");
    div.className="review-item";
    const stars='★'.repeat(review.rating)+'☆'.repeat(5-review.rating);
    div.innerHTML = `<div class="review-header">
      <div class="review-rating-container">
        <span class="review-rating">${stars}</span>
        <span class="rating-label">${ratingTexts[review.rating]}</span>
      </div>
      <span class="review-date">${review.date}</span>
    </div>
    <p class="review-feedback">${review.feedback}</p>`;
    container.appendChild(div);
  });
}

// Initial display
displayReviews();

// Star hover text
document.querySelectorAll('.star-rating label').forEach(label => {
  label.addEventListener('mouseover', function() {
    const ratingText = document.querySelector('.rating-text');
    const value = this.previousElementSibling.value;
    const texts = {'5':'Excellent','4':'Very Good','3':'Good','2':'Fair','1':'Poor'};
    ratingText.textContent = texts[value];
  });
});
document.querySelector('.star-rating').addEventListener('mouseleave', function() {
  const ratingText = document.querySelector('.rating-text');
  const selectedRating = document.querySelector('input[name="rating"]:checked');
  ratingText.textContent = selectedRating ? {'5':'Excellent','4':'Very Good','3':'Good','2':'Fair','1':'Poor'}[selectedRating.value] : 'Select your rating';
});
</script>

</body>
</html>