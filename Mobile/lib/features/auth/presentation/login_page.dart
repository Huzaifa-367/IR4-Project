import 'package:auto_route/auto_route.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:ir4_mobile/core/theme/app_theme.dart';
import 'package:ir4_mobile/core/widgets/app_background.dart';
import 'package:ir4_mobile/core/widgets/glass_card.dart';
import 'package:ir4_mobile/core/widgets/glossy_button.dart';
import 'package:ir4_mobile/features/auth/presentation/auth_controller.dart';
import 'package:ir4_mobile/features/auth/presentation/auth_state.dart';

@RoutePage()
class LoginPage extends ConsumerStatefulWidget {
  const LoginPage({super.key});

  @override
  ConsumerState<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends ConsumerState<LoginPage> {
  final TextEditingController _baseUrlController = TextEditingController();
  final TextEditingController _emailController = TextEditingController();
  final TextEditingController _passwordController = TextEditingController();
  bool _obscurePassword = true;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      final String? stored =
          await ref.read(authControllerProvider.notifier).readStoredBaseUrl();
      if (stored != null && mounted) {
        _baseUrlController.text = stored;
      }
    });
  }

  @override
  void dispose() {
    _baseUrlController.dispose();
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    await ref.read(authControllerProvider.notifier).login(
          baseUrl: _baseUrlController.text,
          email: _emailController.text,
          password: _passwordController.text,
        );
  }

  @override
  Widget build(BuildContext context) {
    final AuthState authState = ref.watch(authControllerProvider);
    final bool isLoading = authState is AuthLoading;
    final String? message =
        authState is AuthUnauthenticated ? authState.message : null;

    return Scaffold(
      backgroundColor: Colors.transparent,
      body: AppBackground(
        child: SafeArea(
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(24),
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 440),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: <Widget>[
                    const _BrandMark(),
                    const SizedBox(height: 22),
                    const Text(
                      'IR4 Operator',
                      style: TextStyle(
                        fontSize: 30,
                        fontWeight: FontWeight.w700,
                        letterSpacing: -0.8,
                        color: AppTheme.text,
                      ),
                    ),
                    const SizedBox(height: 8),
                    const Text(
                      'Scan equipment QR codes to check items in and out from the field.',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: AppTheme.textMuted, height: 1.4),
                    ),
                    const SizedBox(height: 28),
                    GlassCard(
                      padding: const EdgeInsets.all(22),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: <Widget>[
                          _FieldLabel('Server'),
                          const SizedBox(height: 8),
                          TextField(
                            controller: _baseUrlController,
                            enabled: !isLoading,
                            keyboardType: TextInputType.url,
                            autocorrect: false,
                            decoration: const InputDecoration(
                              hintText: 'https://ir4.site.local',
                              prefixIcon: Icon(Icons.dns_outlined),
                            ),
                          ),
                          const SizedBox(height: 18),
                          _FieldLabel('Email'),
                          const SizedBox(height: 8),
                          TextField(
                            controller: _emailController,
                            enabled: !isLoading,
                            keyboardType: TextInputType.emailAddress,
                            autocorrect: false,
                            decoration: const InputDecoration(
                              hintText: 'you@site.local',
                              prefixIcon: Icon(Icons.alternate_email),
                            ),
                          ),
                          const SizedBox(height: 18),
                          _FieldLabel('Password'),
                          const SizedBox(height: 8),
                          TextField(
                            controller: _passwordController,
                            enabled: !isLoading,
                            obscureText: _obscurePassword,
                            onSubmitted: (_) => _submit(),
                            decoration: InputDecoration(
                              hintText: '••••••••',
                              prefixIcon: const Icon(Icons.lock_outline),
                              suffixIcon: IconButton(
                                onPressed: () => setState(
                                  () => _obscurePassword = !_obscurePassword,
                                ),
                                icon: Icon(
                                  _obscurePassword
                                      ? Icons.visibility_outlined
                                      : Icons.visibility_off_outlined,
                                ),
                              ),
                            ),
                          ),
                          if (message != null) ...<Widget>[
                            const SizedBox(height: 16),
                            _ErrorBanner(message: message),
                          ],
                          const SizedBox(height: 22),
                          GlossyButton(
                            label: 'Sign in',
                            icon: Icons.arrow_forward_rounded,
                            loading: isLoading,
                            onPressed: isLoading ? null : _submit,
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 18),
                    const Text(
                      'On-premise • LAN only',
                      style: TextStyle(color: Color(0xFF5B6779), fontSize: 12),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _BrandMark extends StatelessWidget {
  const _BrandMark();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 84,
      height: 84,
      decoration: BoxDecoration(
        gradient: AppTheme.primaryGradient,
        borderRadius: BorderRadius.circular(24),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: AppTheme.accent.withValues(alpha: 0.5),
            blurRadius: 28,
            offset: const Offset(0, 12),
          ),
        ],
      ),
      child: const Icon(Icons.qr_code_scanner_rounded, color: Colors.white, size: 42),
    );
  }
}

class _FieldLabel extends StatelessWidget {
  const _FieldLabel(this.text);

  final String text;

  @override
  Widget build(BuildContext context) {
    return Text(
      text.toUpperCase(),
      style: const TextStyle(
        color: AppTheme.textMuted,
        fontSize: 11,
        fontWeight: FontWeight.w700,
        letterSpacing: 0.8,
      ),
    );
  }
}

class _ErrorBanner extends StatelessWidget {
  const _ErrorBanner({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppTheme.danger.withValues(alpha: 0.14),
        borderRadius: BorderRadius.circular(AppTheme.radiusSmall),
        border: Border.all(color: AppTheme.danger.withValues(alpha: 0.4)),
      ),
      child: Row(
        children: <Widget>[
          const Icon(Icons.error_outline, color: AppTheme.danger, size: 20),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              message,
              style: const TextStyle(color: AppTheme.danger, fontSize: 13),
            ),
          ),
        ],
      ),
    );
  }
}
