<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Student Portal – Internship System</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
  <style>
  /* ===== Global Reset ===== */
  * { margin: 0; padding: 0; box-sizing: border-box; }

  body {
    font-family: "Inter", sans-serif;
    background:
      radial-gradient(60rem 60rem at -10% -20%, rgba(14,165,168,0.06), transparent 60%),
      radial-gradient(50rem 50rem at 120% -10%, rgba(11,31,58,0.06), transparent 60%),
      #f6f8fb;
    color: #0f172a;
    overflow-x: hidden;
    line-height: 1.7;
    scroll-behavior: smooth;
  }

  :root { --brand:#0ea5a8; --brand-2:#22d3ee; --ink:#0b1f3a; --muted:#475569; --panel:#ffffff; --line:#e5e7eb; }
  ::selection { background: rgba(14,165,168,0.3); }

  /* Topbar */
  header {
    background: #ffffff;
    color: var(--ink);
    padding: 14px 5vw;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--line);
    position: sticky;
    top: 0;
    z-index: 10;
    transition: box-shadow 0.2s ease;
  }
  header.scrolled { box-shadow: 0 8px 24px rgba(2,6,23,0.06); }

  header h1 {
    font-size: 1.4rem;
    font-weight: 800;
    letter-spacing: 0.2px;
  }

  nav a {
    color: #334155;
    margin-left: 18px;
    text-decoration: none;
    font-weight: 600;
    transition: color 0.2s ease;
    position: relative;
  }

  nav a:hover { color: var(--brand); }
  nav a::after { content:""; position:absolute; left:0; bottom:-6px; width:0; height:2px; background:var(--brand); transition:width 0.2s ease; }
  nav a:hover::after { width:100%; }
  nav a:focus { outline: none; }
  nav a:focus-visible { color:var(--brand); }

  /* Hero */
  .hero {
    text-align: left;
    padding: 90px 5vw;
    position: relative;
    color: #ffffff;
    background:var(--ink);
    overflow: hidden;
  }
  .hero::before { content:""; position:absolute; inset:0; background:url('https://images.unsplash.com/photo-1523580846011-d3a5bc25702b?q=80&w=1600&auto=format&fit=crop') center/cover no-repeat; filter:brightness(0.55); }
  .hero::after { content:""; position:absolute; inset:0; background:linear-gradient(90deg, rgba(11,31,58,0.85) 0%, rgba(14,165,168,0.35) 100%); background-size:200% 100%; animation:gradientShift 12s ease-in-out infinite alternate; }
  .hero-inner { position:relative; z-index:1; max-width: 1100px; }
  .hero h2 { font-size: clamp(2rem, 4vw, 3rem); color:#fff; margin-bottom: 12px; }
  .hero p { font-size: 1.05rem; color: #e2e8f0; max-width: 760px; }

  /* Services */
  .services {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 22px;
    padding: 60px 5vw;
    max-width: 1200px;
    margin: 0 auto;
  }

  .service-card {
    background: var(--panel);
    border:1px solid var(--line);
    border-radius: 16px;
    padding: 28px 22px;
    text-align: center;
    box-shadow: 0 10px 24px rgba(2,6,23,0.05);
    transition: transform 0.25s ease, box-shadow 0.25s ease, opacity 0.6s;
    opacity: 0; transform: translateY(18px);
    position: relative;
    overflow: hidden;
  }
  .service-card::before { content:""; position:absolute; top:0; left:0; height:4px; width:0; background:linear-gradient(90deg,var(--brand),var(--brand-2)); transition:width 0.35s ease; }
  .service-card.visible { opacity:1; transform:translateY(0); }
  .service-card:hover { transform: translateY(-6px); box-shadow: 0 16px 32px rgba(2,6,23,0.08); }
  .service-card:hover::before { width:100%; }

  .icon { font-size: 28px; margin-bottom: 14px; color: var(--brand); display:inline-flex; align-items:center; justify-content:center; width:56px; height:56px; border-radius:50%; background:rgba(14,165,168,0.12); box-shadow: inset 0 0 0 2px rgba(14,165,168,0.15); }
  .service-card h3 { font-size: 1.15rem; margin-bottom: 8px; color: var(--ink); font-weight: 800; }
  .service-card p { color: var(--muted); font-size: 0.98rem; margin-bottom: 16px; }

  .btn { background:var(--brand); border: none; color: white; padding: 10px 16px; border-radius: 12px; cursor: pointer; font-weight: 700; transition: filter 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease; }
  .btn:hover { filter: brightness(0.96); transform: translateY(-1px); box-shadow:0 10px 24px rgba(14,165,168,0.25); }
  .btn:active { transform: translateY(0); }
  .btn:focus-visible { outline: 3px solid rgba(14,165,168,0.35); outline-offset:2px; }

  /* Gallery removed */

  footer {
    text-align: center;
    padding: 36px 5vw;
    background: linear-gradient(90deg, var(--ink), #10284f);
    font-size: 0.95rem;
    color: #e2e8f0;
    margin-top: 40px;
    border-top:1px solid rgba(255,255,255,0.06);
  }

  @keyframes gradientShift { 0% { background-position:0% 50%; } 100% { background-position:100% 50%; } }

  @media (max-width: 768px) {
    header { flex-direction: column; gap: 10px; }
    .hero h2 { font-size: 2rem; }
  }
  </style>
</head>
<body>

  <header>
    <h1>Student Portal</h1>
    <nav>
      <a href="#">Home</a>
      <a href="#">My Profile</a>
      <a href="#">Internships</a>
      <a href="#">Contact</a>
    </nav>
  </header>

  <section class="hero">
    <div class="hero-inner">
      <h2>Welcome to Your Student Portal</h2>
      <p>Discover internships, build a standout profile, and connect with companies — all in one place.</p>
    </div>
  </section>

  <section class="services">
    <div class="service-card">
      <div class="icon">🎓</div>
      <h3>Apply for Internships</h3>
      <p>Browse and apply to internship opportunities that match your field of study and interests.</p>
      <button class="btn" onclick="showMessage('Internship application feature coming soon!')">Explore</button>
    </div>

    <div class="service-card">
      <div class="icon">👤</div>
      <h3>Create Professional Profile</h3>
      <p>Build your personal brand with a professional profile visible to recruiters and companies.</p>
      <button class="btn" onclick="showMessage('Profile creation feature coming soon!')">Create Now</button>
    </div>

    <div class="service-card">
      <div class="icon">💬</div>
      <h3>Communicate with Companies</h3>
      <p>Message recruiters, ask questions, and stay informed about career opportunities.</p>
      <button class="btn" onclick="showMessage('Messaging system feature coming soon!')">Start Chat</button>
    </div>

    <div class="service-card">
      <div class="icon">📚</div>
      <h3>Learning & Career Guidance</h3>
      <p>Access training programs, workshops, and personalized career advice from experts.</p>
      <button class="btn" onclick="showMessage('Learning resources coming soon!')">Learn More</button>
    </div>
  </section>

  

  <footer>
    © 2025 Student Internship System — All Rights Reserved.
  </footer>

  <script>
    function showMessage(msg) { alert(msg); }
    // Reveal animation for service cards
    const observer = new IntersectionObserver((entries)=>{
      entries.forEach(e=>{ if(e.isIntersecting){ e.target.classList.add('visible'); observer.unobserve(e.target); } });
    },{threshold:0.15});
    document.querySelectorAll('.service-card').forEach((c,i)=>{ c.style.transitionDelay=(i*70)+'ms'; observer.observe(c); });
    // gallery removed
    // Topbar shadow on scroll
    const topbar = document.querySelector('header');
    const onScroll = ()=>{ if(window.scrollY>10) topbar.classList.add('scrolled'); else topbar.classList.remove('scrolled'); };
    document.addEventListener('scroll', onScroll); onScroll();
  </script>
</body>
</html>
