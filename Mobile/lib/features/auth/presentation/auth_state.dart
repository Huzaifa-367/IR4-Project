import 'package:freezed_annotation/freezed_annotation.dart';
import 'package:ir4_mobile/features/auth/domain/auth_user.dart';

part 'auth_state.freezed.dart';

@freezed
sealed class AuthState with _$AuthState {
  const factory AuthState.initial() = AuthInitial;
  const factory AuthState.loading() = AuthLoading;
  const factory AuthState.authenticated(AuthUser user) = AuthAuthenticated;
  const factory AuthState.unauthenticated({String? message}) = AuthUnauthenticated;
}
