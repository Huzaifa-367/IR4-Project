import 'package:get_it/get_it.dart';
import 'package:ir4_mobile/core/network/api_client.dart';
import 'package:ir4_mobile/core/storage/session_store.dart';
import 'package:ir4_mobile/features/auth/data/auth_repository.dart';
import 'package:ir4_mobile/features/equipment/data/equipment_repository.dart';

final GetIt getIt = GetIt.instance;

void configureDependencies({void Function()? onUnauthorized}) {
  if (getIt.isRegistered<SessionStore>()) {
    return;
  }
  final SessionStore sessionStore = SessionStore();
  final ApiClient apiClient = ApiClient(
    sessionStore: sessionStore,
    onUnauthorized: onUnauthorized,
  );
  getIt
    ..registerSingleton<SessionStore>(sessionStore)
    ..registerSingleton<ApiClient>(apiClient)
    ..registerLazySingleton<AuthRepository>(
      () => AuthRepositoryImpl(
        apiClient: getIt<ApiClient>(),
        sessionStore: getIt<SessionStore>(),
      ),
    )
    ..registerLazySingleton<EquipmentRepository>(
      () => EquipmentRepositoryImpl(apiClient: getIt<ApiClient>()),
    );
}

Future<void> resetDependencies() async {
  await getIt.reset();
}
