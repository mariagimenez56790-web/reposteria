import 'package:flutter/widgets.dart';
import '../config/layout_breakpoints.dart';

class AdaptiveLayout extends StatelessWidget {
  const AdaptiveLayout({
    super.key,
    required this.mobile,
    required this.desktop,
  });
  final WidgetBuilder mobile;
  final WidgetBuilder desktop;

  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, constraints) =>
        constraints.maxWidth < LayoutBreakpoints.desktop
        ? KeyedSubtree(key: const Key('mobile-layout'), child: mobile(context))
        : KeyedSubtree(
            key: const Key('desktop-layout'),
            child: desktop(context),
          ),
  );
}
