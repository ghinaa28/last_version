<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Company Profile — GIS</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
* {margin:0; padding:0; box-sizing:border-box; font-family:'Inter',sans-serif;}
body {background:#f9fafb; color:#1f2937; line-height:1.6;}
a {text-decoration:none; color:inherit;}
img {max-width:100%; display:block;}

/* ===== Navbar ===== */
nav {position:fixed; top:0; left:0; right:0; background:rgba(255,255,255,0.95); backdrop-filter:blur(12px); border-bottom:1px solid #e5e7eb; padding:0.8rem 5vw; display:flex; justify-content:space-between; align-items:center; z-index:1000; transition:all 0.3s;}
nav h1 {font-size:clamp(1.5rem,2.5vw,1.8rem); font-weight:900; color:#059669; cursor:pointer;}
.nav-links {display:flex; gap:1.5rem; flex-wrap:wrap; align-items:center;}
.nav-links button {background:none; border:none; cursor:pointer; font-weight:500; color:#374151; padding:0.6rem 1rem; border-radius:10px; position:relative; transition:color 0.3s, transform 0.3s;}
.nav-links button:hover {color:#059669; transform:translateY(-2px);}
.nav-links button::before {content:''; position:absolute; bottom:0; left:50%; transform:translateX(-50%); width:0%; height:2px; background:linear-gradient(90deg,#10b981,#22d3ee); border-radius:2px; transition:width 0.3s;}
.nav-links button:hover::before {width:100%;}
.auth-buttons {display:flex; gap:0.8rem;}
.signup-btn, .login-btn {padding:0.55rem 1.3rem; font-weight:600; font-size:0.95rem; border-radius:9999px; cursor:pointer; transition:all 0.3s;}
.signup-btn {border:none; background:linear-gradient(90deg,#10b981,#22d3ee); color:white;}
.signup-btn:hover {transform:translateY(-3px) scale(1.05); box-shadow:0 6px 20px rgba(34,211,238,0.3);}
.login-btn {border:1px solid #10b981; background:white; color:#059669;}
.login-btn:hover {transform:translateY(-3px) scale(1.05); background:#f0fdf4;}
.hamburger {display:none; flex-direction:column; cursor:pointer; gap:5px;}
.hamburger span {width:28px; height:3px; background:#059669; border-radius:3px; transition:all 0.3s;}

/* ===== Hero Section ===== */
.hero {display:flex; flex-wrap:wrap; justify-content:center; align-items:center; padding:12vh 5vw; min-height:60vh; background:linear-gradient(135deg,#059669,#2563eb); color:white; position:relative; text-align:center;}
.hero h2 {font-size:clamp(2rem,5vw,3rem); font-weight:900; margin-bottom:1rem;}
.hero p {font-size:clamp(1rem,2vw,1.25rem); margin-bottom:2rem; max-width:600px; margin-inline:auto;}
.hero button {padding:14px 36px; background:white; color:#059669; border:none; border-radius:9999px; font-weight:600; font-size:1rem; cursor:pointer; transition:all 0.4s;}
.hero button:hover {background:#a7f3d0; transform:translateY(-4px) scale(1.05);}

/* ===== Services Section ===== */
.services-section {background-color:#f7f9fc; padding:80px 20px; text-align:center;}
.services-section h2 {font-size:2.5rem; color:#2f6ce5; margin-bottom:10px; position: relative; display:inline-block;}
.services-section h2::after {content: ''; display:block; height:4px; width:60px; background-color:#2eb872; margin:10px auto 0 auto; border-radius:2px;}
.services-container {display:flex; justify-content:center; gap:30px; flex-wrap:wrap; margin-top:50px;}
.service-card {background:#fff; padding:30px 20px; border-radius:15px; box-shadow:0 10px 25px rgba(0,0,0,0.08); width:300px; transition: transform 0.3s, box-shadow 0.3s, opacity 0.6s; text-align:center; opacity:0; transform:translateY(30px);}
.service-card.visible {opacity:1; transform:translateY(0);}
.service-card img {width:70px; height:70px; object-fit:cover; border-radius:50%; display:block; margin:0 auto 20px;}
.service-card h3 {color:#2eb872; font-size:1.3rem; margin-bottom:10px;}
.service-card p {font-size:0.95rem; color:#555; line-height:1.6;}
.service-card:hover {transform:translateY(-10px); box-shadow:0 15px 30px rgba(0,0,0,0.15);}

/* ===== Gallery Section ===== */
.gallery-section {padding:80px 20px; text-align:center;}
.gallery-section h2 {font-size:2rem; margin-bottom:40px; color:#2f6ce5;}
.gallery-grid {display:grid; grid-template-columns:repeat(auto-fit,minmax(250px,1fr)); gap:20px;}
.gallery-grid img {width:100%; height:200px; object-fit:cover; border-radius:12px; transition:transform 0.3s;}
.gallery-grid img:hover {transform:scale(1.05);}

/* ===== Contact Section ===== */
.contact-section {background:#f8fafc; padding:80px 20px; display:flex; flex-wrap:wrap; justify-content:center; gap:40px;}
.contact-info, .contact-form {flex:1 1 400px;}
.contact-form form {display:flex; flex-direction:column; gap:15px;}
.contact-form input, .contact-form textarea {padding:12px; border-radius:8px; border:1px solid #cbd5e1;}
.contact-form button {padding:14px; border-radius:8px; border:none; background:#2563eb; color:white; cursor:pointer; transition:0.3s;}
.contact-form button:hover {background:#1e40af;}

/* ===== Footer ===== */
footer {text-align:center; padding:30px 0; background:#0f172a; color:#94a3b8; font-size:0.9rem;}

/* ===== Dark Mode ===== */
@media (prefers-color-scheme: dark) {
  body {background:#0f172a; color:#f1f5f9;}
  .services-section {background:#1e293b;}
  .service-card {background:#111827; box-shadow:0 4px 15px rgba(255,255,255,0.05);}
  .gallery-section {background:#1e293b;}
  .contact-section {background:#111827;}
  .contact-form input, .contact-form textarea {background:#1e293b; color:#f1f5f9; border:1px solid #334155;}
  .contact-form button {background:#22d3ee; color:#0f172a;}
  .contact-form button:hover {background:#0c4a6e; color:#f1f5f9;}
  footer {background:#0f172a; color:#94a3b8;}
}

/* ===== Responsive ===== */
@media(max-width:900px){.hero{padding:10vh 3vw;}.services-container,.gallery-grid{gap:20px;}}
@media(max-width:768px){.contact-section{flex-direction:column;}}
</style>
</head>
<body>

<nav>
<h1>GIS</h1>
<div class="nav-links">
<button onclick="scrollToSection('hero')">Home</button>
<button onclick="scrollToSection('services')">Services</button>
<button onclick="scrollToSection('gallery')">Gallery</button>
<button onclick="scrollToSection('contact')">Contact</button>
</div>
<div class="auth-buttons">
<button class="signup-btn">Sign Up</button>
<button class="login-btn">Login</button>
</div>
<div class="hamburger"><span></span><span></span><span></span></div>
</nav>

<!-- Hero -->
<section class="hero" id="hero">
<h2>BrightTech Solutions</h2>
<p>Empowering companies with internships, trainers, talent search, and more.</p>
<button>Get Started</button>
</section>

<!-- Services -->
<section class="services-section" id="services">
<h2>Our Services</h2>
<div class="services-container">
<div class="service-card">
<img src="https://images.unsplash.com/photo-1581091215364-7248b3c6d2d0?auto=format&fit=crop&w=80&q=80" alt="Post Internship">
<h3>Post Internship Opportunities</h3>
<p>Companies can post internship opportunities to attract top student talent.</p>
</div>
<div class="service-card">
<img src="https://images.unsplash.com/photo-1551836022-9b13a8a9bb4c?auto=format&fit=crop&w=80&q=80" alt="Hire Trainers">
<h3>Hire Trainers / Experts</h3>
<p>Hire industry professionals to provide training and expert guidance.</p>
</div>
<div class="service-card">
<img src="https://images.unsplash.com/photo-1601597110536-6e8adbd2a43b?auto=format&fit=crop&w=80&q=80" alt="Talent Search">
<h3>Talent Search & Recruitment</h3>
<p>Search for and recruit top candidates to fulfill your organizational needs.</p>
</div>
<div class="service-card">
<img src="https://images.unsplash.com/photo-1599058917217-64e52e19f3e3?auto=format&fit=crop&w=80&q=80" alt="Branding">
<h3>Company Profile & Branding</h3>
<p>Enhance your company's image and visibility to attract the best talent.</p>
</div>
<div class="service-card">
<img src="https://images.unsplash.com/photo-1581091215364-7248b3c6d2d0?auto=format&fit=crop&w=80&q=80" alt="Collaboration">
<h3>Collaboration with Universities/Associations</h3>
<p>Partner with educational institutions and associations to find the right candidates.</p>
</div>
<div class="service-card">
<img src="https://images.unsplash.com/photo-1573497491208-6b1acb260507?auto=format&fit=crop&w=80&q=80" alt="Events">
<h3>Event & Job Fair Participation</h3>
<p>Participate in job fairs and events to meet potential interns and employees.</p>
</div>
<div class="service-card">
<img src="https://images.unsplash.com/photo-1599058917217-64e52e19f3e3?auto=format&fit=crop&w=80&q=80" alt="Analytics">
<h3>Analytics & Reports</h3>
<p>Track your internship programs and recruitment campaigns with detailed analytics.</p>
</div>
</div>
</section>

<!-- Gallery -->
<section class="gallery-section" id="gallery">
<h2>Company Gallery</h2>
<div class="gallery-grid">
<img src="https://images.unsplash.com/photo-1521791136064-7986c2920216?auto=format&fit=crop&w=400&q=80" alt="Office">
<img src="https://images.unsplash.com/photo-1522202176988-66273c2fd55f?auto=format&fit=crop&w=400&q=80" alt="Team">
<img src="https://images.unsplash.com/photo-1599058917217-64e52e19f3e3?auto=format&fit=crop&w=400&q=80" alt="Meeting">
<img src="https://images.unsplash.com/photo-1581091215364-7248b3c6d2d0?auto=format&fit=crop&w=400&q=80" alt="Workspace">
</div>
</section>

<!-- Contact -->
<section class="contact-section" id="contact">
<div class="contact-info">
<h2>Contact Us</h2>
<p><strong>Email:</strong> <a href="mailto:info@company.com">info@company.com</a></p>
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
</section>

<footer>
&copy; 2025 BrightTech Solutions | <a href="#">Team Project</a>
</footer>

<script>
// Hamburger toggle
const hamburger = document.querySelector('.hamburger');
const navLinks = document.querySelector('.nav-links');
hamburger.addEventListener('click', ()=> navLinks.classList.toggle('active'));

// Smooth scroll
function scrollToSection(id){
  const sec = document.getElementById(id);
  if(sec) sec.scrollIntoView({behavior:'smooth', block:'start'});
}

// Animate service cards on scroll
const cards = document.querySelectorAll('.service-card');
const observer = new IntersectionObserver(entries=>{
  entries.forEach(entry=>{
    if(entry.isIntersecting) entry.target.classList.add('visible');
  });
},{threshold:0.2});
cards.forEach(card=>observer.observe(card));
</script>

</body>
</html>
