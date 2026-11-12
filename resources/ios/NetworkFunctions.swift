import Foundation
import Network

/// Plugin init function (nativephp.json `ios.init_function`), run once at app
/// launch. Starts the path monitor so changes are observed from launch rather
/// than from the first `Network.Status` call.
func networkInit() {
    NetworkFunctions.startObserving()
}

// MARK: - Network Function Namespace

/// Functions related to network connectivity monitoring
/// Namespace: "Network.*"
enum NetworkFunctions {

    /// Sent to PHP when the status changes. Matches the PHP class in
    /// src/Events/NetworkStatusChanged.php.
    static let statusChangedEvent = "NativePHP\\Network\\Events\\NetworkStatusChanged"

    static func startObserving() {
        _ = PathObserver.shared
    }

    // MARK: - Status

    /// The fields `Network.Status` returns and `NetworkStatusChanged` carries.
    struct NetworkStatus: Equatable {
        let connected: Bool
        let type: String
        let isExpensive: Bool
        let isConstrained: Bool

        init(path: NWPath) {
            connected = path.status == .satisfied
            type = NetworkStatus.interfaceType(of: path)
            isExpensive = path.isExpensive
            isConstrained = path.isConstrained
        }

        var payload: [String: Any] {
            [
                "connected": connected,
                "type": type,
                "isExpensive": isExpensive,
                "isConstrained": isConstrained
            ]
        }

        /// A path can use several interfaces at once; they are checked in the
        /// order that answers "what is this connection, to a person".
        private static func interfaceType(of path: NWPath) -> String {
            if path.usesInterfaceType(.wifi) {
                return "wifi"
            }

            if path.usesInterfaceType(.cellular) {
                return "cellular"
            }

            if path.usesInterfaceType(.wiredEthernet) {
                return "ethernet"
            }

            return "unknown"
        }
    }

    // MARK: - Path Observation

    /// The device's current network path, kept current in the background.
    ///
    /// `NWPathMonitor` is a push API and `BridgeFunction.execute` is
    /// synchronous, so the two are bridged by holding the latest path here and
    /// answering from it. The monitor runs for the life of the process rather
    /// than being started per call: starting one costs a round trip through
    /// the network daemon before it delivers anything, which would put that
    /// cost on every call and still answer late.
    ///
    /// The one call that can arrive before the first path does is the first
    /// one, so that call — and only that call — waits briefly for it.
    ///
    /// Every later path whose status differs from the last one is sent to PHP
    /// as `NetworkStatusChanged`. The first path is the baseline and is not
    /// sent, and paths that change nothing PHP can see (a new address on the
    /// same Wi-Fi, say) are dropped.
    private final class PathObserver {
        static let shared = PathObserver()

        private let monitor = NWPathMonitor()
        private let queue = DispatchQueue(label: "com.nativephp.network.path")
        private let lock = NSLock()
        private let firstPath = DispatchSemaphore(value: 0)

        private var latest: NWPath?
        private var signalled = false

        /// Only touched on `queue`, where path updates arrive one at a time.
        private var reported: NetworkStatus?

        private init() {
            monitor.pathUpdateHandler = { [weak self] path in
                guard let self else { return }

                self.lock.lock()
                self.latest = path
                let isFirst = !self.signalled
                self.signalled = true
                self.lock.unlock()

                if isFirst {
                    self.firstPath.signal()
                }

                self.reportIfChanged(NetworkStatus(path: path))
            }

            monitor.start(queue: queue)
        }

        /// The latest path, waiting up to `timeout` for the first one only.
        /// Returns nil if none has arrived yet.
        func currentPath(waitingUpTo timeout: TimeInterval) -> NWPath? {
            lock.lock()
            let known = latest
            lock.unlock()

            if let known {
                return known
            }

            _ = firstPath.wait(timeout: .now() + timeout)

            lock.lock()
            defer { lock.unlock() }

            return latest
        }

        private func reportIfChanged(_ status: NetworkStatus) {
            guard let previous = reported else {
                reported = status
                return
            }

            guard status != previous else { return }
            reported = status

            DispatchQueue.main.async {
                LaravelBridge.shared.send?(NetworkFunctions.statusChangedEvent, status.payload)
            }
        }
    }

    // MARK: - Network.Status

    /// Get the current network connection status
    /// Parameters: None
    /// Returns:
    ///   - connected: boolean - Whether device has network connectivity
    ///   - type: string - Connection type (wifi, cellular, ethernet, unknown)
    ///   - isExpensive: boolean - Whether connection is metered/cellular
    ///   - isConstrained: boolean - Whether Low Data Mode is enabled
    ///   - error: string - Present only when no path is known yet; the other
    ///     fields are then placeholders, not an observation that the device
    ///     is offline
    class Status: BridgeFunction {
        /// Only ever paid once, before the monitor's first delivery.
        private static let firstPathTimeout: TimeInterval = 0.5

        func execute(parameters: [String: Any]) throws -> [String: Any] {
            guard let path = PathObserver.shared.currentPath(waitingUpTo: Status.firstPathTimeout) else {
                return [
                    "connected": false,
                    "type": "unknown",
                    "isExpensive": false,
                    "isConstrained": false,
                    "error": "Network path not yet determined"
                ]
            }

            return NetworkStatus(path: path).payload
        }
    }
}
