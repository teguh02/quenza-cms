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
    let hasBackup = false;

    try {
        const marker = JSON.parse(await fs.readFile(markerPath, 'utf8'));
        hasBackup = marker.hasBackup === true;
    } catch {
        hasBackup = false;
    }

    if (hasBackup) {
        await fs.copyFile(backupPath, envPath);
    } else {
        await removeIfExists(envPath);
    }

    await removeIfExists(sqlitePath);
    await removeIfExists(markerPath);
    await removeIfExists(backupPath);
};
