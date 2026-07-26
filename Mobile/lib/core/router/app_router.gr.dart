// dart format width=80
// GENERATED CODE - DO NOT MODIFY BY HAND

// **************************************************************************
// AutoRouterGenerator
// **************************************************************************

// ignore_for_file: type=lint
// coverage:ignore-file

part of 'app_router.dart';

/// generated route for
/// [EquipmentDetailPage]
class EquipmentDetailRoute extends PageRouteInfo<EquipmentDetailRouteArgs> {
  EquipmentDetailRoute({
    Key? key,
    required String qrToken,
    List<PageRouteInfo>? children,
  }) : super(
         EquipmentDetailRoute.name,
         args: EquipmentDetailRouteArgs(key: key, qrToken: qrToken),
         rawPathParams: {'qrToken': qrToken},
         initialChildren: children,
       );

  static const String name = 'EquipmentDetailRoute';

  static PageInfo page = PageInfo(
    name,
    builder: (data) {
      final pathParams = data.inheritedPathParams;
      final args = data.argsAs<EquipmentDetailRouteArgs>(
        orElse: () =>
            EquipmentDetailRouteArgs(qrToken: pathParams.getString('qrToken')),
      );
      return EquipmentDetailPage(key: args.key, qrToken: args.qrToken);
    },
  );
}

class EquipmentDetailRouteArgs {
  const EquipmentDetailRouteArgs({this.key, required this.qrToken});

  final Key? key;

  final String qrToken;

  @override
  String toString() {
    return 'EquipmentDetailRouteArgs{key: $key, qrToken: $qrToken}';
  }

  @override
  bool operator ==(Object other) {
    if (identical(this, other)) return true;
    if (other is! EquipmentDetailRouteArgs) return false;
    return key == other.key && qrToken == other.qrToken;
  }

  @override
  int get hashCode => key.hashCode ^ qrToken.hashCode;
}

/// generated route for
/// [LoginPage]
class LoginRoute extends PageRouteInfo<void> {
  const LoginRoute({List<PageRouteInfo>? children})
    : super(LoginRoute.name, initialChildren: children);

  static const String name = 'LoginRoute';

  static PageInfo page = PageInfo(
    name,
    builder: (data) {
      return const LoginPage();
    },
  );
}

/// generated route for
/// [ScanPage]
class ScanRoute extends PageRouteInfo<void> {
  const ScanRoute({List<PageRouteInfo>? children})
    : super(ScanRoute.name, initialChildren: children);

  static const String name = 'ScanRoute';

  static PageInfo page = PageInfo(
    name,
    builder: (data) {
      return const ScanPage();
    },
  );
}
