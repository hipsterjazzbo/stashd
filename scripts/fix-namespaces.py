#!/usr/bin/env python3
"""Fix remaining namespace references after feature-first migration."""

from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

REPLACEMENTS = [
    (r"App\Domain\Broadcast\Contract", r"App\Broadcasts\Formats"),
    (r"App\Services\Broadcast\Types", r"App\Broadcasts\Formats"),
    (r"App\Domain\Broadcast", r"App\Broadcasts"),
    (r"App\Services\Broadcast", r"App\Broadcasts"),
    (r"App\Domain\MediaServer\Contract", r"App\MediaServers"),
    (r"App\Domain\MediaServer", r"App\MediaServers"),
    (r"App\Services\MediaServer", r"App\MediaServers"),
    (r"App\Infrastructure\MediaServer", r"App\MediaServers\Http"),
    (r"App\Domain\Stash", r"App\Stashes"),
    (r"App\Services\Stash", r"App\Stashes"),
    (r"App\Domain\Provider\Fake", r"App\Providers\Fake"),
    (r"App\Domain\Provider\YouTube", r"App\Providers\YouTube"),
    (r"App\Domain\Provider", r"App\Providers"),
    (r"App\Services\Provider", r"App\Providers"),
    (r"App\Infrastructure\Provider", r"App\Providers\Http"),
    (r"App\Domain\Download\Fake", r"App\Downloads\Fake"),
    (r"App\Domain\Download\Ytdlp", r"App\Downloads\Ytdlp"),
    (r"App\Domain\Download", r"App\Downloads"),
    (r"App\Services\Download", r"App\Downloads"),
    (r"App\Domain\Vault", r"App\Vault"),
    (r"App\Domain\Media", r"App\Vault"),
    (r"App\Services\Vault", r"App\Vault"),
    (r"App\Domain\Command", r"App\Commands"),
    (r"App\Services\Command\Handlers", r"App\Commands\Handlers"),
    (r"App\Services\Command", r"App\Commands"),
    (r"App\Domain\Job", r"App\Jobs"),
    (r"App\Services\Job\Handlers", r"App\Jobs\Handlers"),
    (r"App\Services\Job", r"App\Jobs"),
    (r"App\Domain\Auth", r"App\Auth"),
    (r"App\Services\Auth", r"App\Auth"),
    (r"App\Domain\Activity", r"App\System\Activity"),
    (r"App\Domain\Event", r"App\System\Event"),
    (r"App\Domain\Secret", r"App\System\Secret"),
    (r"App\Domain\Storage", r"App\System\Storage"),
    (r"App\Services\Boot", r"App\System\Boot"),
    (r"App\Services\Health", r"App\System\Health"),
    (r"App\Services\Storage", r"App\System\Storage"),
    (r"App\Services\Scheduler", r"App\System\Scheduler"),
    (r"App\Services\Activity", r"App\System\Activity"),
    (r"App\Services\Event", r"App\System\Event"),
    (r"App\Services\Secret", r"App\System\Secret"),
    (r"App\Services\State", r"App\System\State"),
    (r"App\Bootstrap", r"App\System\Wiring"),
    (r"App\Infrastructure\RoadRunner", r"App\System\RoadRunner"),
    (r"App\Domain\Support", r"App\Support"),
    (r"App\Controllers\AuthController", r"App\Auth\AuthController"),
    (r"App\Controllers\BroadcastController", r"App\Broadcasts\BroadcastController"),
    (r"App\Controllers\CommandController", r"App\Commands\CommandController"),
    (r"App\Controllers\JobController", r"App\Jobs\JobController"),
    (r"App\Controllers\MediaItemController", r"App\Vault\MediaItemController"),
    (r"App\Controllers\MediaServerController", r"App\MediaServers\MediaServerController"),
    (r"App\Controllers\StashPreflightController", r"App\Stashes\StashPreflightController"),
    (r"App\Controllers\HealthController", r"App\System\Health\HealthController"),
    (r"App\Controllers\EventsController", r"App\System\Event\EventsController"),
]

REPO_MAP = {
    "BroadcastRepository": r"App\Broadcasts",
    "BroadcastItemRepository": r"App\Broadcasts",
    "BroadcastTriggerRepository": r"App\Broadcasts",
    "BroadcastTriggerRunRepository": r"App\Broadcasts",
    "MediaServerConnectionRepository": r"App\MediaServers",
    "StashInputRepository": r"App\Stashes",
    "StashItemRepository": r"App\Stashes",
    "StashRepository": r"App\Stashes",
    "AssetRepository": r"App\Vault",
    "MediaItemRepository": r"App\Vault",
    "MediaItemSourceRepository": r"App\Vault",
    "CommandRepository": r"App\Commands",
    "JobRepository": r"App\Jobs",
    "UserRepository": r"App\Auth",
    "ApiTokenRepository": r"App\Auth",
    "ActivityEventRepository": r"App\System\Activity",
    "EventNotificationRepository": r"App\System\Event",
    "SecretRepository": r"App\System\Secret",
    "StorageCheckRepository": r"App\System\Storage",
    "StorageLocationRepository": r"App\System\Storage",
    "RecordTimestamps": r"App\Support",
}

CLASS_FIXES = [
    ("BroadcastPlanner", "BroadcastLifecycleService"),
    ("InodeHelper", "HardlinkPublisher"),
    ("DiscoveredItemSerializer::", "DiscoveredItem::"),
    ("RoutingDownloader", "DelegatingDownloader"),
    ("YtdlphpDownloadAdapter", "YouTubeYtdlpDownloadStrategy"),
    ("MediaServerTokenResolver", "MediaServerConnectionSecrets"),
    ("PreflightExecutor", "DiscoverStashInput"),
    ("StashFromPreflightService", "CreateStashFromDiscovery"),
    ("DownloadExecutor", "DownloadMediaItem"),
    ("TempStagingService", "StageDownloadFiles"),
    ("AtomicFileMover", "MoveFileIntoVault"),
    ("VaultVerifyService", "VerifyVaultAssets"),
    ("BroadcastTypeHandler", "BroadcastFormat"),
    ("SeriesBroadcastProfile", "SeriesFormatOptions"),
]

FILE_RENAMES = {
    "app/Downloads/RoutingDownloader.php": "app/Downloads/DelegatingDownloader.php",
    "app/Providers/YouTube/YtdlphpDownloadAdapter.php": "app/Providers/YouTube/YouTubeYtdlpDownloadStrategy.php",
    "app/MediaServers/MediaServerTokenResolver.php": "app/MediaServers/MediaServerConnectionSecrets.php",
    "app/Stashes/PreflightExecutor.php": "app/Stashes/DiscoverStashInput.php",
    "app/Stashes/StashFromPreflightService.php": "app/Stashes/CreateStashFromDiscovery.php",
    "app/Downloads/DownloadExecutor.php": "app/Downloads/DownloadMediaItem.php",
    "app/Vault/TempStagingService.php": "app/Vault/StageDownloadFiles.php",
    "app/Vault/AtomicFileMover.php": "app/Vault/MoveFileIntoVault.php",
    "app/Vault/VaultVerifyService.php": "app/Vault/VerifyVaultAssets.php",
    "app/Broadcasts/Formats/SeriesBroadcastProfile.php": "app/Broadcasts/Formats/SeriesFormatOptions.php",
}


def fix_file(path: Path) -> bool:
    content = path.read_text()
    original = content
    for old, new in REPLACEMENTS:
        content = content.replace(old, new)
    for repo, ns in REPO_MAP.items():
        content = content.replace(rf"App\Infrastructure\Persistence\{repo}", f"{ns}\\{repo}")
    for old, new in CLASS_FIXES:
        content = content.replace(old, new)
    if content != original:
        path.write_text(content)
        return True
    return False


def main() -> None:
    changed = 0
    for base in [ROOT / "app", ROOT / "tests", ROOT / "bin"]:
        if not base.exists():
            continue
        for path in base.rglob("*.php"):
            if fix_file(path):
                changed += 1
                print(f"fixed {path.relative_to(ROOT)}")

    for old, new in FILE_RENAMES.items():
        src = ROOT / old
        dst = ROOT / new
        if src.exists() and not dst.exists():
            src.rename(dst)
            print(f"renamed {old} -> {new}")

    print(f"done ({changed} files updated)")


if __name__ == "__main__":
    main()
