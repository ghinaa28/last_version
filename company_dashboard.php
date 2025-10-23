<?php
session_start();
include "connection.php";

// Check if user is logged in as company
if (!isset($_SESSION['company_id'])) {
    header("Location: login.php");
    exit();
}

// Get company information
$company_id = $_SESSION['company_id'];
$stmt = $conn->prepare("SELECT * FROM companies WHERE company_id = ?");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$company = $stmt->get_result()->fetch_assoc();

if (!$company) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Get company statistics
$stats_sql = "SELECT 
    COUNT(*) as total_internships,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_internships,
    (SELECT COUNT(*) FROM internship_applications ia 
     JOIN internships i ON ia.internship_id = i.internship_id 
     WHERE i.company_id = ?) as total_applications
    FROM internships 
    WHERE company_id = ?";

$stmt = $conn->prepare($stats_sql);
$stmt->bind_param("ii", $company_id, $company_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();


?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Company Portal – Internship System</title>
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

  .user-info {
    display: flex;
    align-items: center;
    gap: 15px;
  }

  .user-name {
    font-weight: 600;
    color: var(--ink);
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

  .logout-btn {
    background: #ff4757;
    color: white;
    padding: 8px 16px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s ease;
  }

  .logout-btn:hover {
    background: #ff3742;
  }

  /* Hero */
  .hero {
    text-align: left;
    padding: 90px 5vw;
    position: relative;
    color: #ffffff;
    background:var(--ink);
    overflow: hidden;
  }
  .hero::before { content:""; position:absolute; inset:0; background:url('https://images.unsplash.com/photo-1521737604893-d14cc237f11d?q=80&w=1600&auto=format&fit=crop') center/cover no-repeat; filter:brightness(0.55); }
  .hero::after { content:""; position:absolute; inset:0; background:linear-gradient(90deg, rgba(11,31,58,0.85) 0%, rgba(14,165,168,0.35) 100%); background-size:200% 100%; animation:gradientShift 12s ease-in-out infinite alternate; }
  .hero-inner { position:relative; z-index:1; max-width: 1100px; }
  .hero h2 { font-size: clamp(2rem, 4vw, 3rem); color:#fff; margin-bottom: 12px; }
  .hero p { font-size: 1.05rem; color: #e2e8f0; max-width: 760px; }

  /* Message Styles */
  .message-container {
    margin: 20px 5vw;
    max-width: 1200px;
    margin-left: auto;
    margin-right: auto;
    animation: slideDown 0.3s ease-out;
  }

  @keyframes slideDown {
    from {
      opacity: 0;
      transform: translateY(-20px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  .message-content {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 20px;
    border-radius: 12px;
    font-weight: 600;
    position: relative;
  }

  .success-message .message-content {
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
    border: 1px solid #10b981;
    color: #065f46;
  }

  .error-message .message-content {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    border: 1px solid #ef4444;
    color: #991b1b;
  }

  .message-icon {
    font-size: 1.2rem;
  }

  .message-text {
    flex: 1;
  }

  .message-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    padding: 0;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: background 0.2s ease;
  }

  .success-message .message-close {
    color: #065f46;
  }

  .error-message .message-close {
    color: #991b1b;
  }

  .message-close:hover {
    background: rgba(0, 0, 0, 0.1);
  }

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

  /* Modern Instructor Service Styles */
  .section-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 2rem;
    padding: 1.5rem;
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    border-radius: 16px;
    border: 1px solid #e2e8f0;
  }

  .section-icon {
    font-size: 2.5rem;
    background: linear-gradient(135deg, var(--brand) 0%, var(--brand-2) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
  }

  .section-content {
    flex: 1;
  }

  .modern-card {
    position: relative;
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    border: 2px solid #e2e8f0;
    transition: all 0.3s ease;
  }

  .modern-card:hover {
    border-color: var(--brand);
    box-shadow: 0 20px 40px rgba(14, 165, 168, 0.15);
    transform: translateY(-8px);
  }

  .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
  }

  .modern-icon {
    font-size: 2rem;
    background: linear-gradient(135deg, var(--brand) 0%, var(--brand-2) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
  }

  .card-badge {
    background: linear-gradient(135deg, var(--brand) 0%, var(--brand-2) 100%);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
  }

  .modern-btn {
    background: linear-gradient(135deg, var(--brand) 0%, var(--brand-2) 100%);
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 12px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
    text-decoration: none;
  }

  .modern-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(14, 165, 168, 0.3);
    color: white;
  }

  .modern-btn i {
    transition: transform 0.3s ease;
  }

  .modern-btn:hover i {
    transform: translateX(4px);
  }

  /* Modern Card Styles */
  .section-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 2rem;
    padding: 1.5rem;
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    border-radius: 16px;
    border: 1px solid #e2e8f0;
  }

  .section-icon {
    font-size: 2.5rem;
    background: linear-gradient(135deg, var(--brand) 0%, var(--brand-2) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
  }

  .section-content {
    flex: 1;
  }

  .section-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--ink);
    margin-bottom: 0.5rem;
  }

  .section-subtitle {
    color: var(--muted);
    font-size: 1rem;
    margin: 0;
  }

  .modern-card {
    position: relative;
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    border: 2px solid #e2e8f0;
    transition: all 0.3s ease;
  }

  .modern-card:hover {
    border-color: var(--brand);
    box-shadow: 0 20px 40px rgba(14, 165, 168, 0.15);
    transform: translateY(-8px);
  }

  .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
  }

  .modern-icon {
    font-size: 2rem;
    background: linear-gradient(135deg, var(--brand) 0%, var(--brand-2) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
  }

  .card-badge {
    background: linear-gradient(135deg, var(--brand) 0%, var(--brand-2) 100%);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
  }

  .modern-btn {
    background: linear-gradient(135deg, var(--brand) 0%, var(--brand-2) 100%);
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 12px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
    text-decoration: none;
    cursor: pointer;
  }

  .modern-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(14, 165, 168, 0.3);
    color: white;
  }

  .modern-btn i {
    transition: transform 0.3s ease;
  }

  .modern-btn:hover i {
    transform: translateX(4px);
  }

  .icon { font-size: 28px; margin-bottom: 14px; color: var(--brand); display:inline-flex; align-items:center; justify-content:center; width:56px; height:56px; border-radius:50%; background:rgba(14,165,168,0.12); box-shadow: inset 0 0 0 2px rgba(14,165,168,0.15); }
  .service-card h3 { font-size: 1.15rem; margin-bottom: 8px; color: var(--ink); font-weight: 800; }
  .service-card p { color: var(--muted); font-size: 0.98rem; margin-bottom: 16px; }

  .btn { background:var(--brand); border: none; color: white; padding: 10px 16px; border-radius: 12px; cursor: pointer; font-weight: 700; transition: filter 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease; }
  .btn:hover { filter: brightness(0.96); transform: translateY(-1px); box-shadow:0 10px 24px rgba(14,165,168,0.25); }
  .btn:active { transform: translateY(0); }
  .btn:focus-visible { outline: 3px solid rgba(14,165,168,0.35); outline-offset:2px; }


  .status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
  }

  .status-approved { background: #d4edda; color: #155724; }
  .status-pending { background: #fff3cd; color: #856404; }
  .status-rejected { background: #f8d7da; color: #721c24; }


  /* Analytics Section */
  .analytics {
    background: var(--panel);
    border: 1px solid var(--line);
    border-radius: 16px;
    padding: 32px;
    margin: 40px 5vw;
    max-width: 1200px;
    margin-left: auto;
    margin-right: auto;
    box-shadow: 0 10px 24px rgba(2,6,23,0.05);
    position: relative;
    overflow: hidden;
  }

  .analytics::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    height: 4px;
    width: 100%;
    background: linear-gradient(90deg, var(--brand), var(--brand-2));
  }

  .analytics h3 {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--ink);
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 12px;
  }

  .analytics h3::before {
    content: "📊";
    font-size: 1.2rem;
  }

  .stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
  }

  .stat-card {
    background: linear-gradient(135deg, rgba(14,165,168,0.05), rgba(34,211,238,0.05));
    border: 1px solid rgba(14,165,168,0.1);
    border-radius: 12px;
    padding: 24px 20px;
    text-align: center;
    transition: transform 0.25s ease, box-shadow 0.25s ease;
    position: relative;
    overflow: hidden;
  }

  .stat-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--brand), var(--brand-2));
  }

  .stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 28px rgba(14,165,168,0.15);
  }

  .stat-number {
    font-size: 2.5rem;
    font-weight: 900;
    color: var(--brand);
    margin-bottom: 8px;
    text-shadow: 0 2px 4px rgba(14,165,168,0.1);
  }

  .stat-label {
    font-size: 0.9rem;
    color: var(--muted);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .account-status {
    background: linear-gradient(135deg, rgba(248,250,252,0.8), rgba(241,245,249,0.8));
    border: 1px solid rgba(226,232,240,0.5);
    border-radius: 12px;
    padding: 20px;
    text-align: center;
  }

  .account-status h4 {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--ink);
    margin-bottom: 12px;
  }

  .status-badge-large {
    display: inline-block;
    padding: 12px 24px;
    border-radius: 25px;
    font-size: 1rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
  }

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
    .user-info { flex-direction: column; gap: 10px; }
  }
  </style>
</head>
<body>

  <header>
    <h1>Company Portal</h1>
    <div class="user-info">
      <nav>
        <a href="company_profile.php">Company Profile</a>
        <a href="manage_locations.php">Manage Locations</a>
        <a href="manage_equipment.php">Manage Equipment</a>
        <a href="collaboration_request.php">Collaboration Requests</a>
        <a href="#analytics">Analytics</a>
      </nav>
      <a href="logout.php" class="logout-btn">Logout</a>
    </div>
  </header>

  <section class="hero">
    <div class="hero-inner">
      <h2 class="user-name">Welcome, <?php echo htmlspecialchars($company['company_name']); ?>!</h2>
      <p>Post internships, manage applications, and connect with talented students — all from your company portal.</p>
    </div>
  </section>



  <!-- Internship Management Section -->
  <section class="services" id="internships">
    <div class="section-header">
      <div class="section-icon">🎓</div>
      <div class="section-content">
        <h2 class="section-title">Internship Management</h2>
        <p class="section-subtitle">Create and manage internship opportunities for students</p>
      </div>
    </div>
    
    <div class="service-card modern-card">
      <div class="card-header">
        <div class="icon modern-icon">📝</div>
        <div class="card-badge">Create</div>
      </div>
      <h3>Post New Internship</h3>
      <p>Create and publish new internship opportunities to attract talented students</p>
      <a href="post_internship.php" class="btn modern-btn">
        <span>Post Internship</span>
        <i class="fas fa-arrow-right"></i>
      </a>
    </div>

    <div class="service-card modern-card">
      <div class="card-header">
        <div class="icon modern-icon">📋</div>
        <div class="card-badge">Manage</div>
      </div>
      <h3>Manage Internships</h3>
      <p>View, edit, and manage your posted internship opportunities and track applications</p>
      <a href="manage_internships.php" class="btn modern-btn">
        <span>Manage Internships</span>
        <i class="fas fa-arrow-right"></i>
      </a>
    </div>

    <div class="service-card modern-card">
      <div class="card-header">
        <div class="icon modern-icon">👥</div>
        <div class="card-badge">Review</div>
      </div>
      <h3>Manage Applications</h3>
      <p>Review, shortlist, and manage student applications for your internships</p>
      <a href="manage_applications.php" class="btn modern-btn">
        <span>View Applications</span>
        <i class="fas fa-arrow-right"></i>
      </a>
    </div>
  </section>

  <!-- Venue Management Section -->
  <section class="services" id="venues">
    <div class="section-header">
      <div class="section-icon">🏢</div>
      <div class="section-content">
        <h2 class="section-title">Venue Management</h2>
        <p class="section-subtitle">Manage your spaces and discover venues from other companies</p>
      </div>
    </div>
    
    <div class="service-card modern-card">
      <div class="card-header">
        <div class="icon modern-icon">🏢</div>
        <div class="card-badge">Post</div>
      </div>
      <h3>Post a Place</h3>
      <p>Showcase your available spaces for other companies to book and use</p>
      <a href="post_place.php" class="btn modern-btn">
        <span>Post Place</span>
        <i class="fas fa-arrow-right"></i>
      </a>
    </div>

    <div class="service-card modern-card">
      <div class="card-header">
        <div class="icon modern-icon">🔍</div>
        <div class="card-badge">Browse</div>
      </div>
      <h3>Browse Places</h3>
      <p>Find and book places from other companies for your events and meetings</p>
      <a href="browse_places.php" class="btn modern-btn">
        <span>Browse Places</span>
        <i class="fas fa-arrow-right"></i>
      </a>
    </div>

    <div class="service-card modern-card">
      <div class="card-header">
        <div class="icon modern-icon">📊</div>
        <div class="card-badge">Manage</div>
      </div>
      <h3>Manage Places</h3>
      <p>Manage your posted places, view bookings, and track performance</p>
      <a href="manage_places.php" class="btn modern-btn">
        <span>Manage Places</span>
        <i class="fas fa-arrow-right"></i>
      </a>
    </div>
  </section>

  <!-- Teaching Solutions Section -->
  <section class="services" id="teaching">
    <div class="section-header">
      <div class="section-icon">👨‍🏫</div>
      <div class="section-content">
        <h2 class="section-title">Teaching Solutions</h2>
        <p class="section-subtitle">Connect with expert instructors for your training needs</p>
      </div>
    </div>
    
    <div class="service-card modern-card">
      <div class="card-header">
        <div class="icon modern-icon">📝</div>
        <div class="card-badge">Request</div>
      </div>
      <h3>Post Teaching Request</h3>
      <p>Find qualified instructors for your courses and training programs</p>
      <a href="post_instructor_request.php" class="btn modern-btn">
        <span>Get Started</span>
        <i class="fas fa-arrow-right"></i>
      </a>
    </div>

    <div class="service-card modern-card">
      <div class="card-header">
        <div class="icon modern-icon">📊</div>
        <div class="card-badge">Manage</div>
      </div>
      <h3>Manage Requests</h3>
      <p>Track applications and manage your instructor opportunities</p>
      <a href="manage_instructor_requests.php" class="btn modern-btn">
        <span>View Dashboard</span>
        <i class="fas fa-arrow-right"></i>
      </a>
    </div>
  </section>

  <!-- Evaluation & Analytics Section -->
  <section class="services" id="evaluation">
    <div class="section-header">
      <div class="section-icon">⭐</div>
      <div class="section-content">
        <h2 class="section-title">Evaluation & Analytics</h2>
        <p class="section-subtitle">Review performance and track your company's impact</p>
      </div>
    </div>
    
    <div class="service-card modern-card">
      <div class="card-header">
        <div class="icon modern-icon">⭐</div>
        <div class="card-badge">Review</div>
      </div>
      <h3>Evaluate Instructors</h3>
      <p>Review and rate instructors who have applied to your teaching opportunities</p>
      <a href="evaluate_instructors.php" class="btn modern-btn">
        <span>Evaluate Instructors</span>
        <i class="fas fa-arrow-right"></i>
      </a>
    </div>

    <div class="service-card modern-card">
      <div class="card-header">
        <div class="icon modern-icon">🏢</div>
        <div class="card-badge">Rate</div>
      </div>
      <h3>Evaluate Places</h3>
      <p>Review and rate places you have booked from other companies</p>
      <a href="evaluate_places.php" class="btn modern-btn">
        <span>Evaluate Places</span>
        <i class="fas fa-arrow-right"></i>
      </a>
    </div>

    <div class="service-card modern-card">
      <div class="card-header">
        <div class="icon modern-icon">📊</div>
        <div class="card-badge">Analytics</div>
      </div>
      <h3>Analytics & Reports</h3>
      <p>Track your internship performance and application metrics</p>
      <a href="#analytics" class="btn modern-btn">
        <span>View Analytics</span>
        <i class="fas fa-arrow-right"></i>
      </a>
    </div>

    <div class="service-card modern-card">
      <div class="card-header">
        <div class="icon modern-icon">🏆</div>
        <div class="card-badge">Certify</div>
      </div>
      <h3>Manage Certificates</h3>
      <p>Issue professional certificates to students who complete courses with your company</p>
      <a href="manage_certificates.php" class="btn modern-btn">
        <span>Manage Certificates</span>
        <i class="fas fa-arrow-right"></i>
      </a>
    </div>
  </section>



  <!-- Analytics Section -->
  <section class="analytics" id="analytics">
    <h3>Company Analytics</h3>
    
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-number"><?php echo $stats['total_internships']; ?></div>
        <div class="stat-label">Total Internships</div>
      </div>
      
      <div class="stat-card">
        <div class="stat-number"><?php echo $stats['active_internships']; ?></div>
        <div class="stat-label">Active Internships</div>
      </div>
      
      <div class="stat-card">
        <div class="stat-number"><?php echo $stats['total_applications']; ?></div>
        <div class="stat-label">Total Applications</div>
      </div>
    </div>
    
    <div class="account-status">
      <h4>Account Status</h4>
      <span class="status-badge-large <?php echo $company['status'] === 'approved' ? 'status-approved' : ($company['status'] === 'pending' ? 'status-pending' : 'status-rejected'); ?>">
        <?php echo ucfirst($company['status']); ?>
      </span>
    </div>
  </section>


  <footer>
    © 2025 Company Internship System — All Rights Reserved.
  </footer>

  <script>
    function showMessage(msg) { alert(msg); }
    
    // Reveal animation for service cards
    const observer = new IntersectionObserver((entries)=>{
      entries.forEach(e=>{ if(e.isIntersecting){ e.target.classList.add('visible'); observer.unobserve(e.target); } });
    },{threshold:0.15});
    document.querySelectorAll('.service-card').forEach((c,i)=>{ c.style.transitionDelay=(i*70)+'ms'; observer.observe(c); });
    
    // Topbar shadow on scroll
    const topbar = document.querySelector('header');
    const onScroll = ()=>{ if(window.scrollY>10) topbar.classList.add('scrolled'); else topbar.classList.remove('scrolled'); };
    document.addEventListener('scroll', onScroll); onScroll();
    
    // Smooth scroll for analytics link
    document.querySelector('a[href="#analytics"]').addEventListener('click', function(e) {
      e.preventDefault();
      document.querySelector('#analytics').scrollIntoView({
        behavior: 'smooth',
        block: 'start'
      });
    });

  </script>
</body>
</html>
