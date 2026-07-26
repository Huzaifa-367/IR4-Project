// GENERATED CODE - DO NOT MODIFY BY HAND
// coverage:ignore-file
// ignore_for_file: type=lint
// ignore_for_file: unused_element, deprecated_member_use, deprecated_member_use_from_same_package, use_function_type_syntax_for_parameters, unnecessary_const, avoid_init_to_null, invalid_override_different_default_values_named, prefer_expression_function_bodies, annotate_overrides, invalid_annotation_target, unnecessary_question_mark

part of 'equipment_state.dart';

// **************************************************************************
// FreezedGenerator
// **************************************************************************

// dart format off
T _$identity<T>(T value) => value;
/// @nodoc
mixin _$EquipmentState {





@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is EquipmentState);
}


@override
int get hashCode => runtimeType.hashCode;

@override
String toString() {
  return 'EquipmentState()';
}


}

/// @nodoc
class $EquipmentStateCopyWith<$Res>  {
$EquipmentStateCopyWith(EquipmentState _, $Res Function(EquipmentState) __);
}


/// Adds pattern-matching-related methods to [EquipmentState].
extension EquipmentStatePatterns on EquipmentState {
/// A variant of `map` that fallback to returning `orElse`.
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case final Subclass value:
///     return ...;
///   case _:
///     return orElse();
/// }
/// ```

@optionalTypeArgs TResult maybeMap<TResult extends Object?>({TResult Function( EquipmentIdle value)?  idle,TResult Function( EquipmentLoading value)?  loading,TResult Function( EquipmentLoaded value)?  loaded,TResult Function( EquipmentError value)?  error,required TResult orElse(),}){
final _that = this;
switch (_that) {
case EquipmentIdle() when idle != null:
return idle(_that);case EquipmentLoading() when loading != null:
return loading(_that);case EquipmentLoaded() when loaded != null:
return loaded(_that);case EquipmentError() when error != null:
return error(_that);case _:
  return orElse();

}
}
/// A `switch`-like method, using callbacks.
///
/// Callbacks receives the raw object, upcasted.
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case final Subclass value:
///     return ...;
///   case final Subclass2 value:
///     return ...;
/// }
/// ```

@optionalTypeArgs TResult map<TResult extends Object?>({required TResult Function( EquipmentIdle value)  idle,required TResult Function( EquipmentLoading value)  loading,required TResult Function( EquipmentLoaded value)  loaded,required TResult Function( EquipmentError value)  error,}){
final _that = this;
switch (_that) {
case EquipmentIdle():
return idle(_that);case EquipmentLoading():
return loading(_that);case EquipmentLoaded():
return loaded(_that);case EquipmentError():
return error(_that);}
}
/// A variant of `map` that fallback to returning `null`.
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case final Subclass value:
///     return ...;
///   case _:
///     return null;
/// }
/// ```

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>({TResult? Function( EquipmentIdle value)?  idle,TResult? Function( EquipmentLoading value)?  loading,TResult? Function( EquipmentLoaded value)?  loaded,TResult? Function( EquipmentError value)?  error,}){
final _that = this;
switch (_that) {
case EquipmentIdle() when idle != null:
return idle(_that);case EquipmentLoading() when loading != null:
return loading(_that);case EquipmentLoaded() when loaded != null:
return loaded(_that);case EquipmentError() when error != null:
return error(_that);case _:
  return null;

}
}
/// A variant of `when` that fallback to an `orElse` callback.
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case Subclass(:final field):
///     return ...;
///   case _:
///     return orElse();
/// }
/// ```

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>({TResult Function()?  idle,TResult Function()?  loading,TResult Function( EquipmentScanResult result)?  loaded,TResult Function( String message)?  error,required TResult orElse(),}) {final _that = this;
switch (_that) {
case EquipmentIdle() when idle != null:
return idle();case EquipmentLoading() when loading != null:
return loading();case EquipmentLoaded() when loaded != null:
return loaded(_that.result);case EquipmentError() when error != null:
return error(_that.message);case _:
  return orElse();

}
}
/// A `switch`-like method, using callbacks.
///
/// As opposed to `map`, this offers destructuring.
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case Subclass(:final field):
///     return ...;
///   case Subclass2(:final field2):
///     return ...;
/// }
/// ```

@optionalTypeArgs TResult when<TResult extends Object?>({required TResult Function()  idle,required TResult Function()  loading,required TResult Function( EquipmentScanResult result)  loaded,required TResult Function( String message)  error,}) {final _that = this;
switch (_that) {
case EquipmentIdle():
return idle();case EquipmentLoading():
return loading();case EquipmentLoaded():
return loaded(_that.result);case EquipmentError():
return error(_that.message);}
}
/// A variant of `when` that fallback to returning `null`
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case Subclass(:final field):
///     return ...;
///   case _:
///     return null;
/// }
/// ```

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>({TResult? Function()?  idle,TResult? Function()?  loading,TResult? Function( EquipmentScanResult result)?  loaded,TResult? Function( String message)?  error,}) {final _that = this;
switch (_that) {
case EquipmentIdle() when idle != null:
return idle();case EquipmentLoading() when loading != null:
return loading();case EquipmentLoaded() when loaded != null:
return loaded(_that.result);case EquipmentError() when error != null:
return error(_that.message);case _:
  return null;

}
}

}

/// @nodoc


class EquipmentIdle implements EquipmentState {
  const EquipmentIdle();
  






@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is EquipmentIdle);
}


@override
int get hashCode => runtimeType.hashCode;

@override
String toString() {
  return 'EquipmentState.idle()';
}


}




/// @nodoc


class EquipmentLoading implements EquipmentState {
  const EquipmentLoading();
  






@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is EquipmentLoading);
}


@override
int get hashCode => runtimeType.hashCode;

@override
String toString() {
  return 'EquipmentState.loading()';
}


}




/// @nodoc


class EquipmentLoaded implements EquipmentState {
  const EquipmentLoaded(this.result);
  

 final  EquipmentScanResult result;

/// Create a copy of EquipmentState
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$EquipmentLoadedCopyWith<EquipmentLoaded> get copyWith => _$EquipmentLoadedCopyWithImpl<EquipmentLoaded>(this, _$identity);



@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is EquipmentLoaded&&(identical(other.result, result) || other.result == result));
}


@override
int get hashCode => Object.hash(runtimeType,result);

@override
String toString() {
  return 'EquipmentState.loaded(result: $result)';
}


}

/// @nodoc
abstract mixin class $EquipmentLoadedCopyWith<$Res> implements $EquipmentStateCopyWith<$Res> {
  factory $EquipmentLoadedCopyWith(EquipmentLoaded value, $Res Function(EquipmentLoaded) _then) = _$EquipmentLoadedCopyWithImpl;
@useResult
$Res call({
 EquipmentScanResult result
});




}
/// @nodoc
class _$EquipmentLoadedCopyWithImpl<$Res>
    implements $EquipmentLoadedCopyWith<$Res> {
  _$EquipmentLoadedCopyWithImpl(this._self, this._then);

  final EquipmentLoaded _self;
  final $Res Function(EquipmentLoaded) _then;

/// Create a copy of EquipmentState
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') $Res call({Object? result = null,}) {
  return _then(EquipmentLoaded(
null == result ? _self.result : result // ignore: cast_nullable_to_non_nullable
as EquipmentScanResult,
  ));
}


}

/// @nodoc


class EquipmentError implements EquipmentState {
  const EquipmentError(this.message);
  

 final  String message;

/// Create a copy of EquipmentState
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$EquipmentErrorCopyWith<EquipmentError> get copyWith => _$EquipmentErrorCopyWithImpl<EquipmentError>(this, _$identity);



@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is EquipmentError&&(identical(other.message, message) || other.message == message));
}


@override
int get hashCode => Object.hash(runtimeType,message);

@override
String toString() {
  return 'EquipmentState.error(message: $message)';
}


}

/// @nodoc
abstract mixin class $EquipmentErrorCopyWith<$Res> implements $EquipmentStateCopyWith<$Res> {
  factory $EquipmentErrorCopyWith(EquipmentError value, $Res Function(EquipmentError) _then) = _$EquipmentErrorCopyWithImpl;
@useResult
$Res call({
 String message
});




}
/// @nodoc
class _$EquipmentErrorCopyWithImpl<$Res>
    implements $EquipmentErrorCopyWith<$Res> {
  _$EquipmentErrorCopyWithImpl(this._self, this._then);

  final EquipmentError _self;
  final $Res Function(EquipmentError) _then;

/// Create a copy of EquipmentState
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') $Res call({Object? message = null,}) {
  return _then(EquipmentError(
null == message ? _self.message : message // ignore: cast_nullable_to_non_nullable
as String,
  ));
}


}

// dart format on
