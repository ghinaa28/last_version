<?php
session_start();
include "connection.php";

// Check if user is logged in as instructor
if (!isset($_SESSION['instructor_id'])) {
    header("Location: login.php");
    exit();
}

// Get instructor information
$instructor_id = $_SESSION['instructor_id'];
$stmt = $conn->prepare("SELECT * FROM instructors WHERE instructor_id = ?");
$stmt->bind_param("i", $instructor_id);
$stmt->execute();
$instructor = $stmt->get_result()->fetch_assoc();

if (!$instructor) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Get instructor evaluations
$evaluations_sql = "SELECT 
    e.evaluation_id,
    e.rating,
    e.evaluation_text,
    e.teaching_quality,
    e.communication,
    e.punctuality,
    e.professionalism,
    e.would_recommend,
    e.created_at,
    ir.course_title,
    c.company_name
    FROM instructor_evaluations e
    JOIN instructor_requests ir ON e.instructor_request_id = ir.instructor_request_id
    JOIN companies c ON e.company_id = c.company_id
    WHERE e.instructor_id = ?
    ORDER BY e.created_at DESC
    LIMIT 10";

$stmt = $conn->prepare($evaluations_sql);
$stmt->bind_param("i", $instructor_id);
$stmt->execute();
$evaluations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate average rating
$avg_rating_sql = "SELECT 
    AVG(rating) as avg_rating,
    COUNT(*) as total_evaluations
    FROM instructor_evaluations 
    WHERE instructor_id = ?";

$stmt = $conn->prepare($avg_rating_sql);
$stmt->bind_param("i", $instructor_id);
$stmt->execute();
$rating_stats = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Instructor Portal – Internship System</title>
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
  .hero::before { content:""; position:absolute; inset:0; background:url('https://images.unsplash.com/photo-1551836022-d5d88e9218df?q=80&w=1600&auto=format&fit=crop') center/cover no-repeat; filter:brightness(0.55); }
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

  /* Modern Teaching Opportunities Styles */
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

  /* Evaluation Section Styles */
  .rating-summary {
    margin-bottom: 2rem;
  }

  .summary-card {
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    border: 2px solid #e2e8f0;
    border-radius: 16px;
    padding: 2rem;
    display: flex;
    align-items: center;
    gap: 1.5rem;
    box-shadow: 0 10px 24px rgba(2,6,23,0.05);
  }

  .summary-icon {
    font-size: 3rem;
    color: var(--brand);
  }

  .summary-content h3 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--ink);
    margin-bottom: 0.5rem;
  }

  .average-rating {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 0.5rem;
  }

  .rating-number {
    font-size: 2.5rem;
    font-weight: 900;
    color: var(--brand);
  }

  .stars {
    display: flex;
    gap: 2px;
  }

  .star {
    font-size: 1.5rem;
    color: #d1d5db;
    transition: color 0.2s ease;
  }

  .star.filled {
    color: #fbbf24;
  }

  .total-evaluations {
    color: var(--muted);
    font-size: 0.9rem;
  }

  .evaluations-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 2rem;
  }

  .evaluation-card {
    position: relative;
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    border: 2px solid #e2e8f0;
    border-radius: 16px;
    padding: 2rem;
    transition: all 0.3s ease;
  }

  .evaluation-card:hover {
    border-color: var(--brand);
    box-shadow: 0 20px 40px rgba(14, 165, 168, 0.15);
    transform: translateY(-8px);
  }

  .company-name {
    color: var(--muted);
    margin-bottom: 1rem;
  }

  .rating-display {
    margin: 1rem 0;
  }

  .detailed-ratings {
    background: rgba(14, 165, 168, 0.05);
    border: 1px solid rgba(14, 165, 168, 0.1);
    border-radius: 8px;
    padding: 1rem;
    margin: 1rem 0;
  }

  .rating-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
  }

  .rating-item:last-child {
    margin-bottom: 0;
  }

  .rating-label {
    font-weight: 500;
    color: var(--ink);
  }

  .rating-value {
    font-weight: 600;
    color: var(--brand);
  }

  .evaluation-text {
    background: rgba(14, 165, 168, 0.05);
    border-left: 3px solid var(--brand);
    padding: 1rem;
    margin: 1rem 0;
    border-radius: 0 8px 8px 0;
    font-style: italic;
  }

  .recommendation {
    margin-top: 1rem;
    text-align: center;
  }

  .recommendation-badge {
    display: inline-block;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 600;
  }

  .recommendation-badge.positive {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
  }

  .recommendation-badge.negative {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
  }

  .no-evaluations {
    text-align: center;
    padding: 4rem 2rem;
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    border: 2px dashed #cbd5e1;
    border-radius: 16px;
    margin: 2rem 0;
  }

  .no-evaluations .icon {
    font-size: 4rem;
    margin-bottom: 1rem;
    color: var(--brand);
  }

  .no-evaluations h3 {
    color: var(--ink);
    margin-bottom: 1rem;
    font-size: 1.5rem;
  }

  .no-evaluations p {
    color: var(--muted);
    margin-bottom: 2rem;
    max-width: 500px;
    margin-left: auto;
    margin-right: auto;
  }

  .bio-section {
    grid-column: 1 / -1;
    margin-top: 20px;
  }

  .bio-text {
    background: #f8fafc;
    padding: 15px;
    border-radius: 12px;
    border-left: 4px solid var(--brand);
    font-style: italic;
    color: var(--muted);
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
    <h1>Instructor Portal</h1>
    <div class="user-info">
      <nav>
        <a href="instructor_profile.php">My Profile</a>
        <a href="manage_courses.php">My Courses</a>
        <a href="browse_places_instructor.php">Browse Places</a>
        <a href="#students">My Students</a>
      </nav>
      <a href="logout.php" class="logout-btn">Logout</a>
    </div>
  </header>

  <section class="hero">
    <div class="hero-inner">
    <h2 class="user-name">Welcome, <?php echo htmlspecialchars($instructor['first_name'] . ' ' . $instructor['last_name']); ?>!</span>
      <p>Guide students, manage internships, and track progress — all from your instructor portal.</p>
    </div>
  </section>


  <section class="services" id="students">
    <div class="service-card">
      <div class="icon">👥</div>
      <h3>My Students</h3>
      <p>View and manage students under your supervision and their internship progress.</p>
      <button class="btn" onclick="showMessage('Student management feature coming soon!')">View Students</button>
    </div>

    <div class="service-card">
      <div class="icon">📝</div>
      <h3>Internship Applications</h3>
      <p>Review and approve student internship applications and recommendations.</p>
      <button class="btn" onclick="showMessage('Application review feature coming soon!')">Review Applications</button>
    </div>

    <div class="service-card">
      <div class="icon">📊</div>
      <h3>Progress Reports</h3>
      <p>Track student progress and generate reports for academic evaluation.</p>
      <button class="btn" onclick="showMessage('Progress tracking feature coming soon!')">View Reports</button>
    </div>

    <div class="service-card">
      <div class="icon">🏢</div>
      <h3>Company Relations</h3>
      <p>Connect with companies and establish partnerships for student internships.</p>
      <button class="btn" onclick="showMessage('Company networking feature coming soon!')">Connect</button>
    </div>

    <div class="service-card">
      <div class="icon">📚</div>
      <h3>Training Materials</h3>
      <p>Access and share training materials and resources with your students.</p>
      <button class="btn" onclick="showMessage('Resource library coming soon!')">Browse Resources</button>
    </div>

    <div class="service-card">
      <div class="icon">💬</div>
      <h3>Communication</h3>
      <p>Communicate with students, companies, and other instructors.</p>
      <button class="btn" onclick="showMessage('Messaging system coming soon!')">View Messages</button>
    </div>
  </section>

  <!-- Course Management Section -->
  <section class="services" id="courses">
    <div class="section-header">
      <div class="section-icon">📚</div>
      <div class="section-content">
        <h2 class="section-title">Course Management</h2>
        <p class="section-subtitle">Create and manage your own courses for students to enroll</p>
      </div>
    </div>
    
    <div class="service-card modern-card">
      <div class="card-header">
        <div class="icon modern-icon">➕</div>
        <div class="card-badge">Create</div>
      </div>
      <h3>Add New Course</h3>
      <p>Create a new course and publish it for students to discover and enroll</p>
      <a href="add_course.php" class="btn modern-btn">
        <span>Create Course</span>
        <i class="fas fa-arrow-right"></i>
      </a>
    </div>

    <div class="service-card modern-card">
      <div class="card-header">
        <div class="icon modern-icon">📊</div>
        <div class="card-badge">Manage</div>
      </div>
      <h3>Manage Courses</h3>
      <p>View, edit, and manage all your courses and track student enrollments</p>
      <a href="manage_courses.php" class="btn modern-btn">
        <span>Manage Courses</span>
        <i class="fas fa-arrow-right"></i>
      </a>
    </div>
  </section>

  <!-- Places & Venues Section -->
  <section class="services" id="places">
    <div class="section-header">
      <div class="section-icon">🏢</div>
      <div class="section-content">
        <h2 class="section-title">Places & Venues</h2>
        <p class="section-subtitle">Browse and apply for places posted by companies</p>
      </div>
    </div>
    
    <div class="service-card modern-card">
      <div class="card-header">
        <div class="icon modern-icon">🔍</div>
        <div class="card-badge">Browse</div>
      </div>
      <h3>Browse Places</h3>
      <p>Find and explore places posted by companies for training, workshops, or events</p>
      <a href="browse_places_instructor.php" class="btn modern-btn">
        <span>Browse Places</span>
        <i class="fas fa-arrow-right"></i>
      </a>
    </div>

    <div class="service-card modern-card">
      <div class="card-header">
        <div class="icon modern-icon">📋</div>
        <div class="card-badge">Track</div>
      </div>
      <h3>My Applications</h3>
      <p>Track your applications for places and manage your venue requests</p>
      <a href="my_place_applications.php" class="btn modern-btn">
        <span>View Applications</span>
        <i class="fas fa-arrow-right"></i>
      </a>
    </div>
  </section>

  <!-- Modern Teaching Opportunities Section -->
  <section class="services" id="opportunities">
    <div class="section-header">
      <div class="section-icon">🎯</div>
      <div class="section-content">
        <h2 class="section-title">Teaching Opportunities</h2>
        <p class="section-subtitle">Discover and apply for instructor positions with leading companies</p>
      </div>
    </div>
    
    <div class="service-card modern-card">
      <div class="card-header">
        <div class="icon modern-icon">🔍</div>
        <div class="card-badge">Explore</div>
      </div>
      <h3>Browse Opportunities</h3>
      <p>Discover exciting teaching opportunities posted by companies and organizations</p>
      <a href="browse_instructor_requests.php" class="btn modern-btn">
        <span>Start Exploring</span>
        <i class="fas fa-arrow-right"></i>
      </a>
    </div>

    <div class="service-card modern-card">
      <div class="card-header">
        <div class="icon modern-icon">📊</div>
        <div class="card-badge">Track</div>
      </div>
      <h3>My Applications</h3>
      <p>Track your applications and manage your teaching opportunities</p>
      <a href="my_instructor_applications.php" class="btn modern-btn">
        <span>View Dashboard</span>
        <i class="fas fa-arrow-right"></i>
      </a>
    </div>
  </section>

  <!-- Instructor Evaluations Section -->
  <section class="services" id="evaluations">
    <div class="section-header">
      <div class="section-icon">⭐</div>
      <div class="section-content">
        <h2 class="section-title">Your Evaluations</h2>
        <p class="section-subtitle">View feedback and ratings from companies you've worked with</p>
      </div>
    </div>
    
    <?php if (!empty($evaluations)): ?>
      <!-- Rating Summary -->
      <div class="rating-summary">
        <div class="summary-card">
          <div class="summary-icon">📊</div>
          <div class="summary-content">
            <h3>Overall Rating</h3>
            <div class="average-rating">
              <span class="rating-number"><?php echo number_format($rating_stats['avg_rating'], 1); ?></span>
              <div class="stars">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                  <span class="star <?php echo $i <= round($rating_stats['avg_rating']) ? 'filled' : ''; ?>">★</span>
                <?php endfor; ?>
              </div>
            </div>
            <p class="total-evaluations">Based on <?php echo $rating_stats['total_evaluations']; ?> evaluation<?php echo $rating_stats['total_evaluations'] != 1 ? 's' : ''; ?></p>
          </div>
        </div>
      </div>

      <!-- Evaluations Grid -->
      <div class="evaluations-grid">
        <?php foreach ($evaluations as $evaluation): ?>
          <div class="service-card modern-card evaluation-card">
            <div class="card-header">
              <div class="icon modern-icon">🏢</div>
              <div class="card-badge"><?php echo date('M d, Y', strtotime($evaluation['created_at'])); ?></div>
            </div>
            
            <h3><?php echo htmlspecialchars($evaluation['course_title']); ?></h3>
            <p class="company-name"><strong>Company:</strong> <?php echo htmlspecialchars($evaluation['company_name']); ?></p>
            
            <div class="rating-display">
              <div class="stars">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                  <span class="star <?php echo $i <= $evaluation['rating'] ? 'filled' : ''; ?>">★</span>
                <?php endfor; ?>
                <span class="rating-number">(<?php echo $evaluation['rating']; ?>/5)</span>
              </div>
            </div>

            <div class="detailed-ratings">
              <div class="rating-item">
                <span class="rating-label">Teaching Quality:</span>
                <span class="rating-value"><?php echo $evaluation['teaching_quality']; ?>/5</span>
              </div>
              <div class="rating-item">
                <span class="rating-label">Communication:</span>
                <span class="rating-value"><?php echo $evaluation['communication']; ?>/5</span>
              </div>
              <div class="rating-item">
                <span class="rating-label">Punctuality:</span>
                <span class="rating-value"><?php echo $evaluation['punctuality']; ?>/5</span>
              </div>
              <div class="rating-item">
                <span class="rating-label">Professionalism:</span>
                <span class="rating-value"><?php echo $evaluation['professionalism']; ?>/5</span>
              </div>
            </div>

            <?php if ($evaluation['evaluation_text']): ?>
              <div class="evaluation-text">
                <strong>Feedback:</strong><br>
                <em>"<?php echo htmlspecialchars($evaluation['evaluation_text']); ?>"</em>
              </div>
            <?php endif; ?>

            <div class="recommendation">
              <?php if ($evaluation['would_recommend']): ?>
                <span class="recommendation-badge positive">✅ Recommended</span>
              <?php else: ?>
                <span class="recommendation-badge negative">❌ Not Recommended</span>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="no-evaluations">
        <div class="icon modern-icon">📝</div>
        <h3>No Evaluations Yet</h3>
        <p>You haven't received any evaluations from companies yet. Once you complete courses and companies provide feedback, your evaluations will appear here.</p>
        <a href="browse_instructor_requests.php" class="btn modern-btn">
          <span>Browse Opportunities</span>
          <span>🔍</span>
        </a>
      </div>
    <?php endif; ?>
  </section>

  <footer>
    © 2025 Instructor Internship System — All Rights Reserved.
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
  </script>
</body>
</html>
