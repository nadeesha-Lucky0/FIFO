  <!-- Public Footer -->
  <footer class="landing-footer">
    <div class="footer-container">
      <div class="footer-brand">
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
          <img src="/assets/images/EGL-NEW.png" alt="EGL Logo" style="height: 40px; width: auto; background: #ffffff; padding: 4px 8px; border-radius: 4px;">
        </div>
        <p>Electricity Generation Lanka (Pvt) Ltd &bull; FIFO Operational Management &amp; Power Generation Dispatch Platform.</p>
      </div>

      <div>
        <h4 class="footer-col-title">Product</h4>
        <ul class="footer-links">
          <li><a href="/index.php#features" class="footer-link">FIFO Engine</a></li>
          <li><a href="/index.php#visualizer" class="footer-link">Batch Routing</a></li>
          <li><a href="/login.php" class="footer-link">Admin Console</a></li>
          <li><a href="/login.php" class="footer-link">Operator Dispatch</a></li>
        </ul>
      </div>

      <div>
        <h4 class="footer-col-title">Security & Roles</h4>
        <ul class="footer-links">
          <li><a href="/login.php" class="footer-link">Role-Based Access</a></li>
          <li><a href="/login.php" class="footer-link">Audit Trails</a></li>
          <li><a href="/login.php" class="footer-link">Session Guard</a></li>
          <li><a href="/login.php" class="footer-link">Compliance</a></li>
        </ul>
      </div>

      <div>
        <h4 class="footer-col-title">System Status</h4>
        <ul class="footer-links">
          <li><span class="system-status-indicator" style="display: inline-flex;"><span class="pulse-dot"></span> All Services Operational</span></li>
          <li style="margin-top: 10px; color: var(--text-muted); font-size: 0.8rem;">PHP 8.2 &bull; SQLite/MySQL &bull; REST API</li>
        </ul>
      </div>
    </div>

    <div class="footer-bottom">
      <div>&copy; <?= date('Y') ?> <?= APP_NAME ?>. All rights reserved. Enterprise Internal Distribution.</div>
      <div>Designed for Scalable Logistics & Warehouse Management</div>
    </div>
  </footer>

  <div id="toast-container"></div>
  <script src="/assets/js/app.js"></script>
</body>
</html>
