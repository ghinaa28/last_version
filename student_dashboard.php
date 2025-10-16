<?php
session_start();
include "connection.php";

// Check if user is logged in as student
if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

// Get student information
$student_id = $_SESSION['student_id'];
$stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if (!$student) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Get instructor evaluations for students to view
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
    c.company_name,
    i.first_name,
    i.last_name,
    i.department
    FROM instructor_evaluations e
    JOIN instructor_requests ir ON e.instructor_request_id = ir.instructor_request_id
    JOIN companies c ON e.company_id = c.company_id
    JOIN instructors i ON e.instructor_id = i.instructor_id
    ORDER BY e.created_at DESC
    LIMIT 20";

$stmt = $conn->prepare($evaluations_sql);
$stmt->execute();
$evaluations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get top rated instructors
$top_instructors_sql = "SELECT 
    i.instructor_id,
    i.first_name,
    i.last_name,
    i.department,
    AVG(e.rating) as avg_rating,
    COUNT(e.evaluation_id) as total_evaluations
    FROM instructors i
    LEFT JOIN instructor_evaluations e ON i.instructor_id = e.instructor_id
    GROUP BY i.instructor_id, i.first_name, i.last_name, i.department
    HAVING total_evaluations > 0
    ORDER BY avg_rating DESC, total_evaluations DESC
    LIMIT 10";

$stmt = $conn->prepare($top_instructors_sql);
$stmt->execute();
$top_instructors = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

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

  /* Policy Information */
  .policy-info {
    padding: 0 5vw;
    max-width: 1200px;
    margin: 0 auto 40px;
  }

  .policy-card {
    background: linear-gradient(135deg, rgba(14,165,168,0.05), rgba(34,211,238,0.05));
    border: 1px solid rgba(14,165,168,0.2);
    border-radius: 16px;
    padding: 24px;
    display: flex;
    align-items: center;
    gap: 20px;
    box-shadow: 0 4px 12px rgba(14,165,168,0.1);
  }

  .policy-icon {
    font-size: 2rem;
    flex-shrink: 0;
  }

  .policy-content h3 {
    color: var(--ink);
    font-size: 1.2rem;
    font-weight: 700;
    margin-bottom: 8px;
  }

  .policy-content p {
    color: var(--muted);
    font-size: 0.95rem;
    line-height: 1.6;
    margin-bottom: 4px;
  }

  .policy-content p:last-child {
    margin-bottom: 0;
  }

  /* Evaluation Section Styles */
  .top-instructors {
    margin-bottom: 2rem;
  }

  .top-instructors h3 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--ink);
    margin-bottom: 1rem;
    text-align: center;
  }

  .instructors-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
  }

  .instructor-card {
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.3s ease;
  }

  .instructor-card:hover {
    border-color: var(--brand);
    box-shadow: 0 10px 20px rgba(14, 165, 168, 0.15);
    transform: translateY(-2px);
  }

  .instructor-info h4 {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--ink);
    margin-bottom: 0.25rem;
  }

  .instructor-info .department {
    color: var(--muted);
    font-size: 0.9rem;
    margin: 0;
  }

  .rating-info {
    text-align: right;
  }

  .stars {
    display: flex;
    gap: 2px;
    margin-bottom: 0.25rem;
  }

  .star {
    font-size: 1rem;
    color: #d1d5db;
    transition: color 0.2s ease;
  }

  .star.filled {
    color: #fbbf24;
  }

  .rating-text {
    font-size: 0.8rem;
    color: var(--muted);
    font-weight: 500;
  }

  .evaluations-section h3 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--ink);
    margin-bottom: 1.5rem;
    text-align: center;
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

  .course-info, .company-info {
    color: var(--muted);
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
  }

  .rating-display {
    margin: 1rem 0;
  }

  .rating-number {
    margin-left: 0.5rem;
    font-weight: 600;
    color: var(--muted);
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
    <h1>Student Portal</h1>
    <div class="user-info">
      <nav>
        <a href="student_profile.php">My Profile</a>
        <a href="my_applications.php">My Applications</a>
        <a href="contact_company.php">Contact Companies</a>
      </nav>
      <a href="logout.php" class="logout-btn">Logout</a>
    </div>
  </header>

  <section class="hero">
    <div class="hero-inner">
      <h2  class="user-name">Welcome, <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>!</h2>
      <p>Discover internships, track your applications, and build your professional profile — all in one place.</p>
    </div>
  </section>


  <section class="services" id="internships">
    <div class="service-card">
      <div class="icon">🎓</div>
      <h3>Browse Internships</h3>
      <p>Explore available internship opportunities that match your field of study and interests.</p>
      <a href="browse_internships.php" class="btn">Browse Now</a>
    </div>

    <div class="service-card">
      <div class="icon">📝</div>
      <h3>My Applications</h3>
      <p>Track your internship applications and their current status.</p>
      <a href="my_applications.php" class="btn">View Applications</a>
    </div>

    <div class="service-card">
      <div class="icon">👤</div>
      <h3>Update Profile</h3>
      <p>Keep your profile information and CV up to date for better opportunities.</p>
      <button class="btn" onclick="showMessage('Profile editing feature coming soon!')">Edit Profile</button>
    </div>

    <div class="service-card">
      <div class="icon">🏆</div>
      <h3>My Certificates</h3>
      <p>View and download your professional certificates from completed courses.</p>
      <a href="my_certificates.php" class="btn">View Certificates</a>
    </div>

   
  </section>

  <!-- Instructor Evaluations Section -->
  <section class="services" id="evaluations">
    <div class="section-header">
      <div class="section-icon">⭐</div>
      <div class="section-content">
        <h2 class="section-title">Instructor Reviews</h2>
        <p class="section-subtitle">View company feedback and ratings for instructors</p>
      </div>
    </div>
    
    <?php if (!empty($evaluations)): ?>
      <!-- Top Instructors Summary -->
      <?php if (!empty($top_instructors)): ?>
        <div class="top-instructors">
          <h3>Top Rated Instructors</h3>
          <div class="instructors-grid">
            <?php foreach (array_slice($top_instructors, 0, 5) as $instructor): ?>
              <div class="instructor-card">
                <div class="instructor-info">
                  <h4><?php echo htmlspecialchars($instructor['first_name'] . ' ' . $instructor['last_name']); ?></h4>
                  <p class="department"><?php echo htmlspecialchars($instructor['department']); ?></p>
                </div>
                <div class="rating-info">
                  <div class="stars">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                      <span class="star <?php echo $i <= round($instructor['avg_rating']) ? 'filled' : ''; ?>">★</span>
                    <?php endfor; ?>
                  </div>
                  <span class="rating-text"><?php echo number_format($instructor['avg_rating'], 1); ?> (<?php echo $instructor['total_evaluations']; ?> reviews)</span>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- Recent Evaluations -->
      <div class="evaluations-section">
        <h3>Recent Instructor Evaluations</h3>
        <div class="evaluations-grid">
          <?php foreach (array_slice($evaluations, 0, 6) as $evaluation): ?>
            <div class="service-card modern-card evaluation-card">
              <div class="card-header">
                <div class="icon modern-icon">👨‍🏫</div>
                <div class="card-badge"><?php echo date('M d, Y', strtotime($evaluation['created_at'])); ?></div>
              </div>
              
              <h3><?php echo htmlspecialchars($evaluation['first_name'] . ' ' . $evaluation['last_name']); ?></h3>
              <p class="course-info"><strong>Course:</strong> <?php echo htmlspecialchars($evaluation['course_title']); ?></p>
              <p class="company-info"><strong>Company:</strong> <?php echo htmlspecialchars($evaluation['company_name']); ?></p>
              
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
      </div>
    <?php else: ?>
      <div class="no-evaluations">
        <div class="icon modern-icon">📝</div>
        <h3>No Evaluations Available</h3>
        <p>There are no instructor evaluations available at the moment. Check back later to see feedback from companies about instructors.</p>
      </div>
    <?php endif; ?>
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
    
    // Topbar shadow on scroll
    const topbar = document.querySelector('header');
    const onScroll = ()=>{ if(window.scrollY>10) topbar.classList.add('scrolled'); else topbar.classList.remove('scrolled'); };
    document.addEventListener('scroll', onScroll); onScroll();
  </script>
</body>
</html>
