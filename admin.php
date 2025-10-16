<?php
session_start();
include "connection.php";



// معالجة الموافقة أو الرفض
if(isset($_GET['action'], $_GET['id'], $_GET['type'])){
    $id = intval($_GET['id']);
    $type = $_GET['type']; // company / instructor
    $action = $_GET['action']; // approve / reject

    if($type === 'company'){
        $table = 'companies';
        $id_field = 'company_id';
    } elseif($type === 'instructor'){
        $table = 'instructors';
        $id_field = 'instructor_id';
    }

    if(isset($table)){
        $status = $action === 'approve' ? 'approved' : 'rejected';
        $stmt = $conn->prepare("UPDATE $table SET status=? WHERE $id_field=?");
        $stmt->bind_param("si", $status, $id);
        $stmt->execute();
    }

   
    
}

// جلب بيانات الشركات والمدرسين
$companies = $conn->query("SELECT company_id, company_name, email, phone, industry, status FROM companies ORDER BY created_at DESC");
$instructors = $conn->query("SELECT instructor_id, CONCAT(first_name, ' ', last_name) as full_name, email, phone, department, university_name, status FROM instructors ORDER BY created_at DESC");

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard - Approvals</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root{
  --primary: #00c6ff;
  --secondary: #00ff9d;
  --bg: #f7f7f7;
  --card-bg: #fff;
  --text-dark: #333;
  --radius: 12px;
  --shadow: 0 4px 20px rgba(0,0,0,0.1);
  --transition: all 0.3s ease;
}
body{
  font-family: 'Inter', sans-serif;
  background: var(--bg);
  margin:0;
  padding:20px;
}
h1{
  text-align:center;
  margin-bottom:20px;
  color: var(--text-dark);
}
.container{
  max-width:1200px;
  margin:0 auto;
}
.card{
  background: var(--card-bg);
  border-radius: var(--radius);
  padding:20px;
  margin-bottom:30px;
  box-shadow: var(--shadow);
}
.card h2{
  margin-bottom:15px;
  color: var(--primary);
}
table{
  width:100%;
  border-collapse: collapse;
}
th, td{
  padding:12px 15px;
  text-align:left;
  border-bottom:1px solid #ddd;
}
th{
  background: linear-gradient(90deg, var(--primary), var(--secondary));
  color:white;
}
tr:hover{
  background:#f0f8ff;
}
button{
  padding:8px 15px;
  border:none;
  border-radius:8px;
  cursor:pointer;
  color:white;
  font-weight:600;
  transition: var(--transition);
}
.approve{
  background: var(--primary);
}
.reject{
  background: #ff4d4f;
}
.approve:hover{
  background: #00a1cc;
}
.reject:hover{
  background: #cc0000;
}
.status{
  padding:5px 10px;
  border-radius:8px;
  font-weight:600;
  color:#fff;
}
.status.pending{background:#ffa500;}
.status.approved{background:#28a745;}
.status.rejected{background:#dc3545;}
</style>
</head>
<body>
<div class="container">
  <h1>Admin Dashboard - Approvals</h1>

  <div class="card">
    <h2>Companies Pending Approval</h2>
    <table>
      <tr>
        <th>Name</th>
        <th>Email</th>
        <th>Phone</th>
        <th>Industry</th>
        <th>Status</th>
        <th>Action</th>
      </tr>
      <?php while($c = $companies->fetch_assoc()): ?>
      <tr>
        <td><?= htmlspecialchars($c['company_name']) ?></td>
        <td><?= htmlspecialchars($c['email']) ?></td>
        <td><?= htmlspecialchars($c['phone']) ?></td>
        <td><?= htmlspecialchars($c['industry']) ?></td>
        <td><span class="status <?= $c['status'] ?>"><?= ucfirst($c['status']) ?></span></td>
        <td>
          <?php if($c['status'] === 'pending'): ?>
          <a href="?action=approve&type=company&id=<?= $c['company_id'] ?>"><button class="approve">Approve</button></a>
          <a href="?action=reject&type=company&id=<?= $c['company_id'] ?>"><button class="reject">Reject</button></a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endwhile; ?>
    </table>
  </div>

  <div class="card">
    <h2>Instructors Pending Approval</h2>
    <table>
      <tr>
        <th>Name</th>
        <th>Email</th>
        <th>Phone</th>
        <th>Department</th>
        <th>University</th>
        <th>Status</th>
        <th>Action</th>
      </tr>
      <?php while($i = $instructors->fetch_assoc()): ?>
      <tr>
        <td><?= htmlspecialchars($i['full_name']) ?></td>
        <td><?= htmlspecialchars($i['email']) ?></td>
        <td><?= htmlspecialchars($i['phone']) ?></td>
        <td><?= htmlspecialchars($i['department']) ?></td>
        <td><?= htmlspecialchars($i['university_name']) ?></td>
        <td><span class="status <?= $i['status'] ?>"><?= ucfirst($i['status']) ?></span></td>
        <td>
          <?php if($i['status'] === 'pending'): ?>
          <a href="?action=approve&type=instructor&id=<?= $i['instructor_id'] ?>"><button class="approve">Approve</button></a>
          <a href="?action=reject&type=instructor&id=<?= $i['instructor_id'] ?>"><button class="reject">Reject</button></a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endwhile; ?>
    </table>
  </div>
</div>
</body>
</html>