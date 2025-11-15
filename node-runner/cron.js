const cron = require('node-cron');
const { spawn } = require('child_process');
const path = require('path');

// Resolve the repository root (parent directory of node-runner)
const repoRoot = path.resolve(__dirname, '..');

// Track if a job is currently running to prevent overlapping executions
let isRunning = false;

/**
 * Execute the tmr command to check for new slots
 */
async function runCheckNewSlots() {
  // Skip if a previous run is still in progress
  if (isRunning) {
    console.log(`[${new Date().toISOString()}] Skipping: Previous run still in progress`);
    return;
  }

  isRunning = true;
  const startTime = new Date().toISOString();
  
  console.log(`[${startTime}] Starting check-new-slots...`);

  return new Promise((resolve, reject) => {
    const phpProcess = spawn('php', ['tmr', 'app:check-new-slots'], {
      cwd: repoRoot,
      stdio: 'inherit',
      shell: process.platform === 'win32' // Use shell on Windows for better compatibility
    });

    phpProcess.on('close', (code) => {
      const endTime = new Date().toISOString();
      
      if (code === 0) {
        console.log(`[${endTime}] Successfully completed check-new-slots`);
        resolve();
      } else {
        console.error(`[${endTime}] Error running check-new-slots: Process exited with code ${code}`);
        reject(new Error(`Process exited with code ${code}`));
      }
      
      isRunning = false;
    });

    phpProcess.on('error', (error) => {
      const endTime = new Date().toISOString();
      console.error(`[${endTime}] Error running check-new-slots:`, error.message);
      isRunning = false;
      reject(error);
    });
  });
}

// Schedule the job to run every hour at minute 0
// Cron expression: '0 * * * *' means "at minute 0 of every hour"
cron.schedule('0 * * * *', runCheckNewSlots);

console.log('Cron scheduler started. Job will run every hour at minute 0.');
console.log('Running initial check...');

// Run immediately on startup
runCheckNewSlots().catch((error) => {
  console.error('Error during initial run:', error.message);
});

