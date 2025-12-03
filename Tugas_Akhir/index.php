<?php
session_start();

// Inisialisasi bandwidth total di session jika belum ada
if (!isset($_SESSION['bandwidth_total'])) {
    $_SESSION['bandwidth_total'] = 100; // Mbps - nilai default
}

// Handle update bandwidth total dari form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_bandwidth_total') {
    $new_bandwidth = (int) ($_POST['bandwidth_total'] ?? 100);
    // Batasi nilai antara 10-1000 Mbps untuk keamanan
    $_SESSION['bandwidth_total'] = max(10, min(1000000, $new_bandwidth));
    $messages[] = "Total bandwidth diperbarui menjadi " . $_SESSION['bandwidth_total'] . " Mbps";
}

// Abstraction
abstract class Perangkat {
    // Encapsulation
    private string $id;
    private string $nama;
    private int $kebutuhan;
    protected string $tipe;

    public function __construct(string $id, string $nama, int $kebutuhan) {
        $this->id = $id;
        $this->nama = $nama;
        $this->setKebutuhan($kebutuhan);
    }

    // Getter & Setter
    public function getId(): string { return $this->id; }
    public function getNama(): string { return $this->nama; }
    public function getTipe(): string { return $this->tipe; }
    public function getKebutuhan(): int { return $this->kebutuhan; }

    public function setNama(string $n): void { $this->nama = $n; }
    public function setKebutuhan(int $k): void {
        // Gunakan bandwidth total dari session
        $bandwidth_total = $_SESSION['bandwidth_total'];
        $this->kebutuhan = max(1, min($bandwidth_total, $k));
    }

    // Polymorphism: setiap perangkat bisa override method ini untuk menentukan bandwidth
    public function hitungKebutuhanAktual(): int {
        return $this->kebutuhan;
    }

    // Abstraksi: menampilkan info perangkat
    public function info(): string {
        return "{$this->nama} ({$this->tipe}) - {$this->kebutuhan} Mbps";
    }
}

// Inheritance
class CCTV extends Perangkat {
    protected string $tipe = 'CCTV';
    public function hitungKebutuhanAktual(): int {
        // Polymorphism: tambahkan 10% untuk overhead
        return (int) ceil($this->getKebutuhan() * 1.1);
    }
}

class Laptop extends Perangkat {
    protected string $tipe = 'Laptop';
}

class Smartphone extends Perangkat {
    protected string $tipe = 'Smartphone';
    // kurangi 10% dari rata-rata
    public function hitungKebutuhanAktual(): int {
        return max(1, (int) floor($this->getKebutuhan() * 0.9));
    }
}

class Server extends Perangkat {
    protected string $tipe = 'Server';
    // tambah 20%
    public function hitungKebutuhanAktual(): int {
        return (int) ceil($this->getKebutuhan() * 1.2);
    }
}

// Router class: mengelola device, queue, stack
class Router {
    private int $bandwidthTotal;

    public function __construct(int $bandwidthTotal) {
        $this->bandwidthTotal = $bandwidthTotal;
    }

    // Hitung total pemakaian dari devices yang aktif
    public function hitungTotalPemakaian(array $activeDevices): int {
        $total = 0;
        foreach ($activeDevices as $dev) {
            $total += $dev->hitungKebutuhanAktual();
        }
        return $total;
    }

    public function getBandwidthTotal(): int { return $this->bandwidthTotal; }
    
    // Tambahkan method untuk update bandwidth total
    public function setBandwidthTotal(int $bandwidthTotal): void {
        $this->bandwidthTotal = $bandwidthTotal;
    }

    // Tentukan status jaringan berdasarkan pemakaian
    public function getStatus(array $activeDevices): string {
        $used = $this->hitungTotalPemakaian($activeDevices);
        if ($used == 0) return 'IDLE';
        if ($used <= $this->bandwidthTotal) return 'STABIL';
        if ($used > $this->bandwidthTotal && $used <= $this->bandwidthTotal * 1.25) return 'OVERLOAD';
        return 'DOWN';
    }
}

// Helper functions (procedural + method-like)

// generate ID 
function genId(): string {
    return uniqid('dev_');
}

// Inisialisasi session storage (perangkat, queue, stack, aktif)
if (!isset($_SESSION['sba'])) {
    $_SESSION['sba'] = [
        'devices' => [], // Perangkat 
        'queue' => [],   // antrian (FIFO)
        'active' => [],  // perangkat yang terhubung
        'stack' => []    // history (LIFO)
    ];

    // Tambahkan beberapa perangkat default (demo)
    $defaults = [
        new CCTV(genId(), 'CCTV-Lobby', 15),
        new Laptop(genId(), 'Laptop-Ahmad', 30),
        new Smartphone(genId(), 'HP-Fathur', 5),
        new Server(genId(), 'Server-DB', 50),
    ];

    foreach ($defaults as $d) {
        $_SESSION['sba']['devices'][$d->getId()] = serialize($d);
    }
}

// Util: get object by id
function getDevice(string $id) {
    if (!isset($_SESSION['sba']['devices'][$id])) return null;
    return unserialize($_SESSION['sba']['devices'][$id]);
}

// Util: save device back to session
function saveDevice($device) {
    $_SESSION['sba']['devices'][$device->getId()] = serialize($device);
}

// Util: convert device list to object list
function objectsFromIds(array $ids): array {
    $res = [];
    foreach ($ids as $id) {
        $o = getDevice($id);
        if ($o !== null) $res[] = $o;
    }
    return $res;
}

// Initialize messages array
$messages = [];

// Handle forms (aksi user) - SEMUA FORM HANDLER DI SINI

// Tambah device baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_device') {
    $type = $_POST['type'] ?? 'Laptop';
    $name = trim($_POST['name'] ?? $type . '-' . rand(1,99));
    $need = (int) ($_POST['need'] ?? 30);
    $id = genId();

    switch ($type) {
        case 'CCTV': $dev = new CCTV($id, $name, $need); break;
        case 'Server': $dev = new Server($id, $name, $need); break;
        case 'Smartphone': $dev = new Smartphone($id, $name, $need); break;
        default: $dev = new Laptop($id, $name, $need); break;
    }

    $_SESSION['sba']['devices'][$id] = serialize($dev);
    $messages[] = "Perangkat baru ditambahkan: " . $dev->info();
}

// Masukkan device ke queue
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'enqueue') {
    $id = $_POST['device_id'] ?? '';
    if ($id && isset($_SESSION['sba']['devices'][$id])) {
        // hindari duplikat di queue
        if (!in_array($id, $_SESSION['sba']['queue']) && !in_array($id, $_SESSION['sba']['active'])) {
            array_push($_SESSION['sba']['queue'], $id); // enqueue (FIFO)
            $messages[] = "Perangkat dimasukkan ke antrian.";
        } else {
            $messages[] = "Perangkat sudah berada di antrian atau aktif.";
        }
    }
}

// Proses antrian: koneksikan ke active & tetap konek walau overload untuk simulasi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process_next') {
    if (!empty($_SESSION['sba']['queue'])) {
        $nextId = array_shift($_SESSION['sba']['queue']); // dequeue
        array_push($_SESSION['sba']['active'], $nextId);
        array_push($_SESSION['sba']['stack'], $nextId); // push ke stack history
        $dev = getDevice($nextId);
        $messages[] = "Memproses antrian: " . ($dev ? $dev->info() : $nextId);
    } else {
        $messages[] = "Antrian kosong.";
    }
}

// Disconnect device dari active
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'disconnect') {
    $id = $_POST['active_id'] ?? '';
    if ($id && in_array($id, $_SESSION['sba']['active'])) {
        // hapus dari active
        $_SESSION['sba']['active'] = array_values(array_filter($_SESSION['sba']['active'], function($v) use ($id){ return $v !== $id; }));
        array_push($_SESSION['sba']['stack'], $id);
        $messages[] = "Perangkat disconnected.";
    }
}

// Pop history show
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'pop_stack') {
    if (!empty($_SESSION['sba']['stack'])) {
        $last = array_pop($_SESSION['sba']['stack']);
        $messages[] = "Mengeluarkan dari history (stack): $last";
    } else {
        $messages[] = "History kosong.";
    }
}

// Reset sistem: membersihkan queue & active & stack
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset') {
    $_SESSION['sba']['queue'] = [];
    $_SESSION['sba']['active'] = [];
    $_SESSION['sba']['stack'] = [];
    $messages[] = "Sistem di-reset.";
}

// Hapus device
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_device') {
    $id = $_POST['del_id'] ?? '';
    if ($id && isset($_SESSION['sba']['devices'][$id])) {
        // hapus jika tidak aktif/antrian
        $_SESSION['sba']['devices'] = array_filter($_SESSION['sba']['devices'], function($k) use ($id) { return $k !== $id; }, ARRAY_FILTER_USE_KEY);
        // remove from queue & active & stack if exists
        $_SESSION['sba']['queue'] = array_values(array_filter($_SESSION['sba']['queue'], fn($v) => $v !== $id));
        $_SESSION['sba']['active'] = array_values(array_filter($_SESSION['sba']['active'], fn($v) => $v !== $id));
        $_SESSION['sba']['stack'] = array_values(array_filter($_SESSION['sba']['stack'], fn($v) => $v !== $id));
        $messages[] = "Perangkat dihapus dari sistem.";
    }
}

// Update kebutuhan device 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_need') {
    $id = $_POST['upd_id'] ?? '';
    $need = (int) ($_POST['upd_need'] ?? 10);
    if ($id && ($dev = getDevice($id))) {
        $dev->setKebutuhan($need);
        saveDevice($dev);
        $messages[] = "Kebutuhan bandwidth diperbarui.";
    }
}

// Prepare data untuk tampilan
$devices = array_map('unserialize', $_SESSION['sba']['devices']);
$queue = $_SESSION['sba']['queue'];
$active = $_SESSION['sba']['active'];
$stack = array_reverse($_SESSION['sba']['stack']); // tampilkan history paling baru dulu
$active_objs = objectsFromIds($active);

// Inisialisasi router dengan bandwidth dari session
$router = new Router($_SESSION['bandwidth_total']);
$used = $router->hitungTotalPemakaian($active_objs);
$status = $router->getStatus($active_objs);
?>

<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Smart Bandwidth Battle Arena</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
  <div class="theme-toggle" id="themeToggle">
    <i class="fas fa-moon"></i>
  </div>
  <div class="wrap">
    <header>
      <h1><i class="fa-solid fa-network-wired"></i> Smart Bandwidth Battle Arena</h1>
      <p class="muted">Simulasi perebutan bandwidth</p>
    </header>

    <main class="grid">
      <section class="panel">
        <h2><i class="fa-solid fa-gauge-high"></i> Router Dashboard</h2>
        
        <!-- Form untuk mengubah bandwidth total -->
        <div class="bandwidth-control">
          <form method="post" class="form-inline">
            <input type="hidden" name="action" value="update_bandwidth_total">
            <label>Total Bandwidth: 
              <input type="number" name="bandwidth_total" 
                     value="<?= $_SESSION['bandwidth_total'] ?>" 
                     min="10" max="1000000" step="10" style="width: 80px;">
              Mbps
            </label>
            <button type="submit" class="small">Update</button>
          </form>
        </div>
        
        <div class="status-row">
          <div>Total Bandwidth</div>
          <div><strong><?= $_SESSION['bandwidth_total'] ?> Mbps</strong></div>
        </div>
        <div class="status-row">
          <div>Digunakan</div>
          <div><strong><?= $used ?> Mbps</strong></div>
        </div>

        <div class="bar-wrap" aria-hidden="true">
          <?php
            $pct = min(100, (int) round(($used / max(1, $_SESSION['bandwidth_total'])) * 100));
          ?>
          <div class="bar">
            <div class="bar-fill" style="width:<?= $pct ?>%"></div>
          </div>
          <div class="status-badge <?= strtolower($status) ?>"><?= $status ?></div>
        </div>

        <div class="controls">
          <form method="post" class="inline">
            <input type="hidden" name="action" value="process_next">
            <button type="submit">Process Next (Dequeue)</button>
          </form>

          <form method="post" class="inline">
            <input type="hidden" name="action" value="pop_stack">
            <button type="submit">Pop History (Stack)</button>
          </form>

          <form method="post" class="inline" onsubmit="return confirm('Reset sistem?');">
            <input type="hidden" name="action" value="reset">
            <button type="submit" class="danger">Reset</button>
          </form>
        </div>

        <?php if (!empty($messages)): ?>
          <div class="messages">
            <?php foreach ($messages as $m): ?>
              <div class="msg">• <?= htmlspecialchars($m) ?></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

      </section>

      <section class="panel">
        <h2><i class="fa-solid fa-microchip"></i> Perangkat </h2>

        <table class="tbl">
          <thead><tr><th>Nama</th><th>Tipe</th><th>Kebutuhan</th><th>Status</th><th>Aksi</th></tr></thead>
          <tbody>
            <?php foreach ($devices as $dev): 
              $id = $dev->getId();
              $st = in_array($id, $active) ? 'Aktif' : (in_array($id, $queue) ? 'Di Antrian' : 'Idle');
            ?>
              <tr>
                <td><?= htmlspecialchars($dev->getNama()) ?></td>
                <td><?= htmlspecialchars($dev->getTipe()) ?></td>
                <td><?= $dev->getKebutuhan() ?> Mbps (aktual <?= $dev->hitungKebutuhanAktual() ?>)</td>
                <td><?= $st ?></td>
                <td class="nowrap">
                  <?php if ($st === 'Idle'): ?>
                    <form method="post" class="inline">
                      <input type="hidden" name="action" value="enqueue">
                      <input type="hidden" name="device_id" value="<?= $id ?>">
                      <button type="submit">Masuk Antrian</button>
                    </form>
                  <?php endif; ?>

                  <?php if ($st === 'Aktif'): ?>
                    <form method="post" class="inline">
                      <input type="hidden" name="action" value="disconnect">
                      <input type="hidden" name="active_id" value="<?= $id ?>">
                      <button type="submit">Disconnect</button>
                    </form>
                  <?php endif; ?>

                  <form method="post" class="inline">
                    <input type="hidden" name="action" value="delete_device">
                    <input type="hidden" name="del_id" value="<?= $id ?>">
                    <button type="submit" class="small danger"><i class="fa-solid fa-trash"></i></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($devices)): ?>
              <tr><td colspan="5">Belum ada perangkat.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>

        <h3>Tambah Perangkat</h3>
        <form method="post" class="form">
          <input type="hidden" name="action" value="add_device">
          <label>Nama <input name="name" placeholder="Nama perangkat"></label>
          <label>Tipe
            <select name="type">
              <option value="Laptop">Laptop</option>
              <option value="Smartphone">Smartphone</option>
              <option value="CCTV">CCTV</option>
              <option value="Server">Server</option>
            </select>
          </label>
          <label>Kebutuhan (Mbps) <input type="number" name="need" value="30" min="1" max="<?= $_SESSION['bandwidth_total'] ?>"></label>
          <button type="submit">Tambah</button>
        </form>

      </section>

      <section class="panel">
        <h2>Antrian (Queue)</h2>
        <ol class="list-queue">
          <?php if (empty($queue)): ?>
            <li>-- Kosong --</li>
          <?php else: ?>
            <?php foreach ($queue as $id): $d = getDevice($id); ?>
              <li><?= $d ? htmlspecialchars($d->info()) : htmlspecialchars($id) ?></li>
            <?php endforeach; ?>
          <?php endif; ?>
        </ol>

        <h2>Active Devices</h2>
        <ul class="list-active">
          <?php if (empty($active_objs)): ?>
            <li>-- Tidak ada aktif --</li>
          <?php else: ?>
            <?php foreach ($active_objs as $a): ?>
              <li><?= htmlspecialchars($a->info()) ?></li>
            <?php endforeach; ?>
          <?php endif; ?>
        </ul>

        <h2>History (Stack)</h2>
        <ol class="list-stack">
          <?php if (empty($stack)): ?>
            <li>-- Kosong --</li>
          <?php else: ?>
            <?php foreach ($stack as $id): $d = getDevice($id); ?>
              <li><?= $d ? htmlspecialchars($d->info()) : htmlspecialchars($id) ?></li>
            <?php endforeach; ?>
          <?php endif; ?>
        </ol>

      </section>

      <section class="panel">
        <h2>Quick Actions & Tuning</h2>

        <h3>Update kebutuhan device</h3>
        <form method="post" class="form-inline">
          <input type="hidden" name="action" value="update_need">
          <select name="upd_id" required>
            <option value="">-- Pilih Perangkat --</option>
            <?php foreach ($devices as $d): ?>
              <option value="<?= $d->getId() ?>"><?= htmlspecialchars($d->getNama()) ?> (<?= $d->getTipe() ?>)</option>
            <?php endforeach; ?>
          </select>
          <input type="number" name="upd_need" value="10" min="1" max="<?= $_SESSION['bandwidth_total'] ?>">
          <button type="submit">Update</button>
        </form>

        <h3>Catatan : </h3>
        <ul class="muted small">
          <li>Queue = FIFO (yang pertama masuk yang diproses dulu)</li>
          <li>Stack = LIFO (history terakhir muncul paling awal)</li>
          <li>Jika penggunaan melebihi kapasitas, status akan menjadi OVERLOAD atau DOWN</li>
        </ul>

      </section>
    </main>
    <footer>
      <small><i class="fa-solid fa-code"></i> By Fathur Arya Susena - 21120125120053 >>> Smart Bandwidth Battle Arena.</small>
    </footer>
  </div>

  <script>
    // Theme toggle functionality 
    document.addEventListener('DOMContentLoaded', function() {
      const themeToggle = document.getElementById('themeToggle');
      const htmlElement = document.documentElement;
      const themeIcon = themeToggle.querySelector('i');
      
      // Check for saved theme preference or respect OS preference
      const savedTheme = localStorage.getItem('theme');
      const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
      
      let currentTheme = savedTheme || (systemPrefersDark ? 'dark' : 'light');
      
      // Apply the saved theme
      htmlElement.setAttribute('data-theme', currentTheme);
      updateThemeIcon(currentTheme);
      
      // Toggle theme on button click
      themeToggle.addEventListener('click', () => {
        currentTheme = currentTheme === 'dark' ? 'light' : 'dark';
        htmlElement.setAttribute('data-theme', currentTheme);
        localStorage.setItem('theme', currentTheme);
        updateThemeIcon(currentTheme);
      });
      
      function updateThemeIcon(theme) {
        if (theme === 'dark') {
          themeIcon.className = 'fas fa-moon';
        } else {
          themeIcon.className = 'fas fa-sun';
        }
      }
    });
  </script>
</body>
</html>