#!/usr/bin/env python3
"""Migrate app/ from Domain/Services/Infrastructure to feature-first layout."""

from __future__ import annotations

import re
import shutil
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
APP = ROOT / "app"
TESTS = ROOT / "tests"

# Files to delete (merged elsewhere) — relative to app/
DELETE_FILES = {
    "Services/Broadcast/BroadcastPlanner.php",
    "Services/Broadcast/BroadcastPublisher.php",
    "Services/Broadcast/BroadcastVerifier.php",
    "Services/Broadcast/BroadcastPruner.php",
    "Services/Broadcast/InodeHelper.php",
    "Services/Stash/DiscoveredItemSerializer.php",
    "Services/Command/Handlers/AbstractBroadcastCommandHandler.php",
    "Services/Command/Handlers/BroadcastPlanCommandHandler.php",
    "Services/Command/Handlers/BroadcastRebuildCommandHandler.php",
    "Services/Command/Handlers/BroadcastVerifyCommandHandler.php",
    "Services/Command/Handlers/BroadcastPruneCommandHandler.php",
    "Services/Command/Handlers/BroadcastTriggerCommandHandler.php",
    "Services/Command/Handlers/AbstractMediaServerCommandHandler.php",
    "Services/Command/Handlers/MediaServerTestConnectionCommandHandler.php",
    "Services/Command/Handlers/MediaServerListLibrariesCommandHandler.php",
    "Domain/Broadcast/Contract/BroadcastTypeHandler.php",
}

# old relative path (from app/) -> new relative path (from app/)
FILE_MOVES: dict[str, str] = {}

def add_move(old: str, new: str) -> None:
    FILE_MOVES[old] = new

add_move("Domain/Broadcast/Contract/BroadcastFormat.php", "Broadcasts/Formats/BroadcastFormat.php")

# --- Support ---
add_move("Domain/Support/PrefixedUlid.php", "Support/PrefixedUlid.php")
add_move("Domain/Support/PrefixedUlidGenerator.php", "Support/PrefixedUlidGenerator.php")
add_move("Infrastructure/Persistence/RecordTimestamps.php", "Support/RecordTimestamps.php")

# --- Commands (domain + core services) ---
for name in ["CommandRecord.php", "CommandState.php", "CommandType.php"]:
    add_move(f"Domain/Command/{name}", f"Commands/{name}")
for name in [
    "CommandDispatchResult.php", "CommandDispatchService.php", "CommandHandler.php",
    "CommandHandlerRegistry.php", "InvalidCommandPayload.php",
]:
    add_move(f"Services/Command/{name}", f"Commands/{name}")
add_move("Infrastructure/Persistence/CommandRepository.php", "Commands/CommandRepository.php")
add_move("Controllers/CommandController.php", "Commands/CommandController.php")

# Command handlers -> features or Commands/Handlers
add_move("Services/Command/Handlers/AssetVerifyCommandHandler.php", "Vault/AssetVerifyCommandHandler.php")
add_move("Services/Command/Handlers/ItemDownloadCommandHandler.php", "Downloads/ItemDownloadCommandHandler.php")
add_move("Services/Command/Handlers/SystemStorageCheckCommandHandler.php", "System/SystemStorageCheckCommandHandler.php")
add_move("Services/Command/Handlers/SystemVerifyVaultCommandHandler.php", "Vault/SystemVerifyVaultCommandHandler.php")
add_move("Services/Command/Handlers/StashCreateFromPreflightCommandHandler.php", "Stashes/StashCreateFromPreflightCommandHandler.php")
add_move("Services/Command/Handlers/StashPreflightCommandHandler.php", "Stashes/StashPreflightCommandHandler.php")

# --- Jobs ---
for name in ["JobIntent.php", "JobRecord.php", "JobState.php"]:
    add_move(f"Domain/Job/{name}", f"Jobs/{name}")
for name in [
    "JobHandlerContext.php", "JobHandler.php", "JobHandlerRegistry.php",
    "JobWorkerCallbacks.php", "JobWorkerService.php",
]:
    add_move(f"Services/Job/{name}", f"Jobs/{name}")
for name in [
    "BootJobHandler.php", "BroadcastJobHandler.php", "CreateFromPreflightJobHandler.php",
    "DownloadJobHandler.php", "MediaServerJobHandler.php", "PreflightJobHandler.php",
    "StorageCheckJobHandler.php", "VerifyVaultJobHandler.php",
]:
    add_move(f"Services/Job/Handlers/{name}", f"Jobs/Handlers/{name}")
add_move("Infrastructure/Persistence/JobRepository.php", "Jobs/JobRepository.php")
add_move("Controllers/JobController.php", "Jobs/JobController.php")

# --- Auth ---
for name in ["ApiTokenRecord.php", "UserRecord.php", "UserRole.php"]:
    add_move(f"Domain/Auth/{name}", f"Auth/{name}")
for name in [
    "AuthContext.php", "AuthenticationRequired.php", "AuthService.php",
    "InvalidCredentials.php", "SetupAlreadyCompleted.php", "SetupRequired.php",
]:
    add_move(f"Services/Auth/{name}", f"Auth/{name}")
add_move("Infrastructure/Persistence/UserRepository.php", "Auth/UserRepository.php")
add_move("Infrastructure/Persistence/ApiTokenRepository.php", "Auth/ApiTokenRepository.php")
add_move("Controllers/AuthController.php", "Auth/AuthController.php")

# --- Broadcasts ---
for name in [
    "BroadcastContext.php", "BroadcastException.php", "BroadcastItemRecord.php",
    "BroadcastItemState.php", "BroadcastPlannedFile.php", "BroadcastPlannedSidecar.php",
    "BroadcastPlan.php", "BroadcastPruneResult.php", "BroadcastPublishResult.php",
    "BroadcastRecord.php", "BroadcastSidecarKind.php", "BroadcastState.php",
    "BroadcastTriggerRecord.php", "BroadcastTriggerRunRecord.php", "BroadcastTriggerRunState.php",
    "BroadcastTriggerState.php", "BroadcastTriggerType.php", "BroadcastType.php",
    "BroadcastVerifyResult.php",
]:
    add_move(f"Domain/Broadcast/{name}", f"Broadcasts/{name}")
for name in [
    "BroadcastContextFactory.php", "BroadcastFilenameBuilder.php", "BroadcastLifecycleService.php",
    "BroadcastNfoBuilder.php", "BroadcastPathBuilder.php", "BroadcastSidecarWriter.php",
    "BroadcastTriggerService.php", "BroadcastTypeRegistry.php", "HardlinkPublisher.php",
]:
    add_move(f"Services/Broadcast/{name}", f"Broadcasts/{name}")
for name in [
    "AbstractSeriesBroadcastType.php", "FilesystemSeriesBroadcastType.php",
    "JellyfinSeriesBroadcastType.php", "PlexSeriesBroadcastType.php", "SeriesBroadcastProfile.php",
]:
    add_move(f"Services/Broadcast/Types/{name}", f"Broadcasts/Formats/{name}")
for name in [
    "BroadcastRepository.php", "BroadcastItemRepository.php",
    "BroadcastTriggerRepository.php", "BroadcastTriggerRunRepository.php",
]:
    add_move(f"Infrastructure/Persistence/{name}", f"Broadcasts/{name}")
add_move("Controllers/BroadcastController.php", "Broadcasts/BroadcastController.php")

# --- MediaServers ---
for name in [
    "MediaServerConnectionRecord.php", "MediaServerConnectionState.php", "MediaServerException.php",
    "MediaServerHttpClient.php", "MediaServerHttpResponse.php", "MediaServerLibraryRef.php",
    "MediaServerStatus.php", "MediaServerTriggerResult.php", "MediaServerType.php",
]:
    add_move(f"Domain/MediaServer/{name}", f"MediaServers/{name}")
add_move("Domain/MediaServer/Contract/MediaServerClient.php", "MediaServers/MediaServerClient.php")
for name in [
    "JellyfinMediaServerClient.php", "MediaServerClientRegistry.php",
    "MediaServerConnectionService.php", "MediaServerTokenResolver.php", "PlexMediaServerClient.php",
]:
    add_move(f"Services/MediaServer/{name}", f"MediaServers/{name}")
for name in ["CurlMediaServerHttpClient.php", "FixtureMediaServerHttpClient.php"]:
    add_move(f"Infrastructure/MediaServer/{name}", f"MediaServers/Http/{name}")
add_move("Infrastructure/Persistence/MediaServerConnectionRepository.php", "MediaServers/MediaServerConnectionRepository.php")
add_move("Controllers/MediaServerController.php", "MediaServers/MediaServerController.php")

# --- Stashes ---
for name in [
    "PreflightOrigin.php", "StashInputRecord.php", "StashInputState.php", "StashInputType.php",
    "StashItemRecord.php", "StashItemState.php", "StashRecord.php", "StashState.php", "StashType.php",
]:
    add_move(f"Domain/Stash/{name}", f"Stashes/{name}")
for name in [
    "PreflightExecutionResult.php", "PreflightExecutor.php", "StashFromPreflightResult.php",
    "StashFromPreflightService.php", "StashInputTypeMapper.php",
]:
    add_move(f"Services/Stash/{name}", f"Stashes/{name}")
for name in ["StashInputRepository.php", "StashItemRepository.php", "StashRepository.php"]:
    add_move(f"Infrastructure/Persistence/{name}", f"Stashes/{name}")
add_move("Controllers/StashPreflightController.php", "Stashes/StashPreflightController.php")

# --- Providers ---
add_move("Domain/Provider/DiscoveredItem.php", "Providers/Core/DiscoveredItem.php")
for name in [
    "DiscoveryStrategyHandler.php", "DownloadStrategyHandler.php", "MetadataStrategyHandler.php",
    "Provider.php", "ProviderAccountRecord.php", "ProviderAccountState.php", "ProviderAuthType.php",
    "ProviderDates.php", "ProviderException.php", "ProviderHttpClient.php", "ProviderHttpResponse.php",
    "ProviderRegistry.php", "ProviderStrategy.php", "ResolvedInput.php", "StashdUri.php",
    "StrategyCost.php", "StrategyPurpose.php",
]:
    add_move(f"Domain/Provider/{name}", f"Providers/{name}")
add_move("Domain/Provider/Fake/FakeProvider.php", "Providers/Fake/FakeProvider.php")
for name in [
    "YouTubeChannelIdResolver.php", "YouTubeDataApiMetadataStrategy.php", "YouTubeInputType.php",
    "YouTubeProvider.php", "YouTubeRssDiscoveryStrategy.php", "YouTubeRssParser.php",
    "YouTubeUriDetector.php", "YouTubeUriResolver.php", "YouTubeUris.php", "YouTubeVideoDiscovery.php",
    "YtdlpDownloadAdapter.php", "YtdlphpDownloadAdapter.php",
]:
    add_move(f"Domain/Provider/YouTube/{name}", f"Providers/YouTube/{name}")
for name in ["ProviderStrategySelector.php", "StrategySelectionOptions.php"]:
    add_move(f"Services/Provider/{name}", f"Providers/{name}")
for name in ["CurlProviderHttpClient.php", "FixtureProviderHttpClient.php"]:
    add_move(f"Infrastructure/Provider/{name}", f"Providers/Http/{name}")

# --- Downloads ---
for name in [
    "DownloadedFile.php", "DownloaderInterface.php", "DownloadException.php",
    "DownloadProbeResult.php", "DownloadRequest.php", "DownloadResult.php", "RoutingDownloader.php",
]:
    add_move(f"Domain/Download/{name}", f"Downloads/{name}")
add_move("Domain/Download/Fake/FakeDownloader.php", "Downloads/Fake/FakeDownloader.php")
for name in [
    "StubYtdlpGateway.php", "YtdlpDownloader.php", "YtdlpGatewayImpl.php",
    "YtdlpGateway.php", "YtdlpOptionsBuilder.php", "YtdlpProbeResult.php",
]:
    add_move(f"Domain/Download/Ytdlp/{name}", f"Downloads/Ytdlp/{name}")
for name in [
    "DownloadExecutionResult.php", "DownloadExecutor.php", "DownloadPolicyEvaluator.php",
]:
    add_move(f"Services/Download/{name}", f"Downloads/{name}")

# --- Vault ---
add_move("Domain/Vault/VaultSidecarBuilder.php", "Vault/VaultSidecarBuilder.php")
for name in [
    "AssetKind.php", "AssetRecord.php", "AssetRole.php", "AssetState.php",
    "MediaItemRecord.php", "MediaItemSourceRecord.php", "MediaItemState.php",
    "MetadataSnapshotType.php", "RawMetadataSnapshotRecord.php", "UpstreamState.php",
]:
    add_move(f"Domain/Media/{name}", f"Vault/{name}")
for name in [
    "AtomicFileMover.php", "TempStagingService.php", "VaultChecksum.php", "VaultPathBuilder.php",
    "VaultVerifyResult.php", "VaultVerifyService.php", "VerifyAssetOutcome.php",
]:
    add_move(f"Services/Vault/{name}", f"Vault/{name}")
for name in ["AssetRepository.php", "MediaItemRepository.php", "MediaItemSourceRepository.php"]:
    add_move(f"Infrastructure/Persistence/{name}", f"Vault/{name}")
add_move("Controllers/MediaItemController.php", "Vault/MediaItemController.php")

# --- System ---
for name in ["ActivityEventRecord.php", "ActivityLevel.php"]:
    add_move(f"Domain/Activity/{name}", f"System/Activity/{name}")
add_move("Domain/Event/EventNotificationRecord.php", "System/Event/EventNotificationRecord.php")
add_move("Domain/Secret/SecretType.php", "System/Secret/SecretType.php")
for name in [
    "StorageCheckRecord.php", "StorageCheckState.php", "StorageLocationRecord.php",
    "StorageLocationState.php", "StorageRootKind.php",
]:
    add_move(f"Domain/Storage/{name}", f"System/Storage/{name}")
for name in ["BootstrapService.php", "MigrationRunner.php", "SqliteConfigurator.php"]:
    add_move(f"Services/Boot/{name}", f"System/Boot/{name}")
add_move("Services/Health/HealthService.php", "System/Health/HealthService.php")
for name in [
    "FilesystemProbe.php", "PathSanitizer.php", "StorageCapabilityChecker.php", "StorageRootService.php",
]:
    add_move(f"Services/Storage/{name}", f"System/Storage/{name}")
add_move("Services/Scheduler/RoutineDiscoveryScheduler.php", "System/Scheduler/RoutineDiscoveryScheduler.php")
add_move("Services/Activity/ActivityEventService.php", "System/Activity/ActivityEventService.php")
add_move("Services/Event/EventPublisher.php", "System/Event/EventPublisher.php")
add_move("Services/Secret/SecretsService.php", "System/Secret/SecretsService.php")
for name in ["InvalidStateTransition.php", "StateTransitionService.php"]:
    add_move(f"Services/State/{name}", f"System/State/{name}")
for name in [
    "CommandHandlerRegistryInitializer.php", "DownloaderInitializer.php",
    "JobHandlerRegistryInitializer.php", "MediaServerHttpClientInitializer.php",
    "ProviderHttpClientInitializer.php", "YtdlpDownloadAdapterInitializer.php",
    "YtdlpGatewayInitializer.php",
]:
    add_move(f"Bootstrap/{name}", f"System/Wiring/{name}")
for name in ["RoadRunnerProcessLauncher.php", "TempestPsr7Bridge.php"]:
    add_move(f"Infrastructure/RoadRunner/{name}", f"System/RoadRunner/{name}")
for name in [
    "ActivityEventRepository.php", "EventNotificationRepository.php", "SecretRepository.php",
    "StorageCheckRepository.php", "StorageLocationRepository.php",
]:
    add_move(f"Infrastructure/Persistence/{name}", f"System/{name.replace('Repository.php', '')}/{name}")

# Fix storage repos path
FILE_MOVES["Infrastructure/Persistence/StorageCheckRepository.php"] = "System/Storage/StorageCheckRepository.php"
FILE_MOVES["Infrastructure/Persistence/StorageLocationRepository.php"] = "System/Storage/StorageLocationRepository.php"
FILE_MOVES["Infrastructure/Persistence/ActivityEventRepository.php"] = "System/Activity/ActivityEventRepository.php"
FILE_MOVES["Infrastructure/Persistence/EventNotificationRepository.php"] = "System/Event/EventNotificationRepository.php"
FILE_MOVES["Infrastructure/Persistence/SecretRepository.php"] = "System/Secret/SecretRepository.php"

add_move("Controllers/HealthController.php", "System/Health/HealthController.php")
add_move("Controllers/EventsController.php", "System/Event/EventsController.php")

# Namespace replacements (order matters — longest first)
NS_REPLACEMENTS: list[tuple[str, str]] = [
    (r"App\\Domain\\Broadcast\\Contract", r"App\\Broadcasts\\Formats"),
    (r"App\\Services\\Broadcast\\Types", r"App\\Broadcasts\\Formats"),
    (r"App\\Domain\\Broadcast", r"App\\Broadcasts"),
    (r"App\\Services\\Broadcast", r"App\\Broadcasts"),
    (r"App\\Domain\\MediaServer\\Contract", r"App\\MediaServers"),
    (r"App\\Domain\\MediaServer", r"App\\MediaServers"),
    (r"App\\Services\\MediaServer", r"App\\MediaServers"),
    (r"App\\Infrastructure\\MediaServer", r"App\\MediaServers\\Http"),
    (r"App\\Domain\\Stash", r"App\\Stashes"),
    (r"App\\Services\\Stash", r"App\\Stashes"),
    (r"App\\Domain\\Provider\\Fake", r"App\\Providers\\Fake"),
    (r"App\\Domain\\Provider\\YouTube", r"App\\Providers\\YouTube"),
    (r"App\\Domain\\Provider", r"App\\Providers"),
    (r"App\\Services\\Provider", r"App\\Providers"),
    (r"App\\Infrastructure\\Provider", r"App\\Providers\\Http"),
    (r"App\\Domain\\Download\\Fake", r"App\\Downloads\\Fake"),
    (r"App\\Domain\\Download\\Ytdlp", r"App\\Downloads\\Ytdlp"),
    (r"App\\Domain\\Download", r"App\\Downloads"),
    (r"App\\Services\\Download", r"App\\Downloads"),
    (r"App\\Domain\\Vault", r"App\\Vault"),
    (r"App\\Domain\\Media", r"App\\Vault"),
    (r"App\\Services\\Vault", r"App\\Vault"),
    (r"App\\Domain\\Command", r"App\\Commands"),
    (r"App\\Services\\Command\\Handlers", r"App\\Commands\\Handlers"),
    (r"App\\Services\\Command", r"App\\Commands"),
    (r"App\\Domain\\Job", r"App\\Jobs"),
    (r"App\\Services\\Job\\Handlers", r"App\\Jobs\\Handlers"),
    (r"App\\Services\\Job", r"App\\Jobs"),
    (r"App\\Domain\\Auth", r"App\\Auth"),
    (r"App\\Services\\Auth", r"App\\Auth"),
    (r"App\\Domain\\Activity", r"App\\System\\Activity"),
    (r"App\\Domain\\Event", r"App\\System\\Event"),
    (r"App\\Domain\\Secret", r"App\\System\\Secret"),
    (r"App\\Domain\\Storage", r"App\\System\\Storage"),
    (r"App\\Services\\Boot", r"App\\System\\Boot"),
    (r"App\\Services\\Health", r"App\\System\\Health"),
    (r"App\\Services\\Storage", r"App\\System\\Storage"),
    (r"App\\Services\\Scheduler", r"App\\System\\Scheduler"),
    (r"App\\Services\\Activity", r"App\\System\\Activity"),
    (r"App\\Services\\Event", r"App\\System\\Event"),
    (r"App\\Services\\Secret", r"App\\System\\Secret"),
    (r"App\\Services\\State", r"App\\System\\State"),
    (r"App\\Bootstrap", r"App\\System\\Wiring"),
    (r"App\\Infrastructure\\RoadRunner", r"App\\System\\RoadRunner"),
    (r"App\\Infrastructure\\Persistence", r"App\\Infrastructure\\Persistence"),  # handled per-file
    (r"App\\Domain\\Support", r"App\\Support"),
    (r"App\\Controllers", r"App\\Controllers"),  # most moved
]

# Per-class renames in use statements and code
CLASS_RENAMES: list[tuple[str, str]] = [
    ("RoutingDownloader", "DelegatingDownloader"),
    ("BroadcastTypeHandler", "BroadcastFormat"),
    ("SeriesBroadcastProfile", "SeriesFormatOptions"),
    ("MediaServerTokenResolver", "MediaServerConnectionSecrets"),
    ("PreflightExecutor", "DiscoverStashInput"),
    ("StashFromPreflightService", "CreateStashFromDiscovery"),
    ("YtdlphpDownloadAdapter", "YouTubeYtdlpDownloadStrategy"),
    ("DownloadExecutor", "DownloadMediaItem"),
    ("TempStagingService", "StageDownloadFiles"),
    ("AtomicFileMover", "MoveFileIntoVault"),
    ("VaultVerifyService", "VerifyVaultAssets"),
    ("InodeHelper::", "HardlinkPublisher::"),
    ("DiscoveredItemSerializer::", "DiscoveredItem::"),
]

REPO_NS_MAP = {
    "BroadcastRepository": "App\\Broadcasts",
    "BroadcastItemRepository": "App\\Broadcasts",
    "BroadcastTriggerRepository": "App\\Broadcasts",
    "BroadcastTriggerRunRepository": "App\\Broadcasts",
    "MediaServerConnectionRepository": "App\\MediaServers",
    "StashInputRepository": "App\\Stashes",
    "StashItemRepository": "App\\Stashes",
    "StashRepository": "App\\Stashes",
    "AssetRepository": "App\\Vault",
    "MediaItemRepository": "App\\Vault",
    "MediaItemSourceRepository": "App\\Vault",
    "CommandRepository": "App\\Commands",
    "JobRepository": "App\\Jobs",
    "UserRepository": "App\\Auth",
    "ApiTokenRepository": "App\\Auth",
    "ActivityEventRepository": "App\\System\\Activity",
    "EventNotificationRepository": "App\\System\\Event",
    "SecretRepository": "App\\System\\Secret",
    "StorageCheckRepository": "App\\System\\Storage",
    "StorageLocationRepository": "App\\System\\Storage",
    "RecordTimestamps": "App\\Support",
}


def path_to_namespace(rel: str) -> str:
    stem = rel.replace(".php", "").replace("/", "\\")
    return f"App\\{stem}"


def update_namespace(content: str, new_ns: str) -> str:
    return re.sub(
        r"^namespace\s+[^;]+;",
        lambda _: f"namespace {new_ns};",
        content,
        count=1,
        flags=re.MULTILINE,
    )


def apply_replacements(content: str) -> str:
    for old, new in NS_REPLACEMENTS:
        if old.endswith("Persistence") or old.endswith("Controllers"):
            continue
        content = content.replace(old, new)

    for class_name, ns in REPO_NS_MAP.items():
        content = content.replace(f"App\\Infrastructure\\Persistence\\{class_name}", f"{ns}\\{class_name}")

    for old, new in CLASS_RENAMES:
        content = re.sub(rf"\b{old}\b", new, content)

    # Controller namespaces for moved controllers
    controller_map = {
        "App\\Controllers\\AuthController": "App\\Auth\\AuthController",
        "App\\Controllers\\BroadcastController": "App\\Broadcasts\\BroadcastController",
        "App\\Controllers\\CommandController": "App\\Commands\\CommandController",
        "App\\Controllers\\JobController": "App\\Jobs\\JobController",
        "App\\Controllers\\MediaItemController": "App\\Vault\\MediaItemController",
        "App\\Controllers\\MediaServerController": "App\\MediaServers\\MediaServerController",
        "App\\Controllers\\StashPreflightController": "App\\Stashes\\StashPreflightController",
        "App\\Controllers\\HealthController": "App\\System\\Health\\HealthController",
        "App\\Controllers\\EventsController": "App\\System\\Event\\EventsController",
    }
    for old, new in controller_map.items():
        content = content.replace(old, new)

    return content


def move_files() -> None:
    for old, new in FILE_MOVES.items():
        src = APP / old
        dst = APP / new
        if not src.exists():
            print(f"SKIP missing: {old}")
            continue
        dst.parent.mkdir(parents=True, exist_ok=True)
        content = src.read_text()
        new_ns = path_to_namespace(new)
        # strip trailing class name from namespace for nested? path_to_namespace includes class file name wrong
        new_ns = "\\".join(new_ns.split("\\")[:-1])
        content = update_namespace(content, new_ns)
        content = apply_replacements(content)
        dst.write_text(content)
        print(f"MOVED {old} -> {new}")


def delete_old() -> None:
    for rel in DELETE_FILES:
        path = APP / rel
        if path.exists():
            path.unlink()
            print(f"DELETED {rel}")
    # remove moved sources
    for old in FILE_MOVES:
        src = APP / old
        if src.exists():
            src.unlink()
            print(f"REMOVED old {old}")


def update_remaining_files() -> None:
    for path in list(APP.rglob("*.php")) + list(TESTS.rglob("*.php")) + [ROOT / "bin" / "worker.php"]:
        if not path.exists():
            continue
        content = path.read_text()
        updated = apply_replacements(content)
        if updated != content:
            path.write_text(updated)
            print(f"UPDATED {path.relative_to(ROOT)}")


def remove_empty_dirs(base: Path) -> None:
    for path in sorted(base.rglob("*"), reverse=True):
        if path.is_dir() and not any(path.iterdir()):
            path.rmdir()
            print(f"RMDIR {path.relative_to(ROOT)}")


def main() -> None:
    move_files()
    delete_old()
    update_remaining_files()
    for folder in ["Domain", "Services", "Infrastructure", "Controllers", "Bootstrap", "Discovery"]:
        remove_empty_dirs(APP / folder)


if __name__ == "__main__":
    main()
