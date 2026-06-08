const fs = require('node:fs/promises');
const path = require('node:path');

const rootPath = __dirname;
const tempDirPath = path.join(rootPath, '.playwright-tmp');
const markerPath = path.join(tempDirPath, 'env-backup.json');
const backupPath = path.join(tempDirPath, '.env.backup');
const envPath = path.join(rootPath, '.env');
const sqlitePath = path.join(rootPath, 'storage', 'database', 'quenza.db');

async function removeIfExists(filePath) {
    await fs.rm(filePath, { force: true });
}

module.exports = async () => {
    await fs.mkdir(tempDirPath, { recursive: true });

    let hasBackup = false;

    try {
        await fs.copyFile(envPath, backupPath);
        hasBackup = true;
    } catch {
        hasBackup = false;
    }

    await fs.writeFile(markerPath, JSON.stringify({ hasBackup }), 'utf8');

    await removeIfExists(envPath);
    await removeIfExists(sqlitePath);
};
